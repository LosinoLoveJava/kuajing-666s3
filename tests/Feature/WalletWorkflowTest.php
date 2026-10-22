<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\SellerWithdrawRequest;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WalletWorkflowTest extends TestCase
{
    use \Tests\Concerns\WalletDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWalletDatabase();
    }

    private function user($role = 'customer')
    {
        $user = new User;
        $user->forceFill(['name' => 'Test '.$role, 'user_type' => $role, 'balance' => '100.00']);
        $user->save();
        return $user;
    }

    private function request(array $data = [], User $user = null)
    {
        $request = Request::create('/wallet', 'POST', $data);
        if ($user) $request->setUserResolver(function () use ($user) { return $user; });
        return $request;
    }

    private function rechargeData($role)
    {
        return ['amount' => '20.50', 'trx_id' => 'BANK-REFERENCE-123', 'payment_option' => 'Test bank transfer',
            'recharge_method' => 'Bank', 'bank_name' => 'Test bank', 'account_holder' => 'Test Holder', 'bank_account' => '123456'];
    }

    private function withdrawalData($method = 4)
    {
        return ['amount' => '20.50', 'w_type' => $method, 'request_token' => (string) Str::uuid(),
            'iban_country' => 'AE', 'iban_holder' => 'Test Holder', 'iban_bank' => 'Test bank',
            'iban_number' => 'AE070331234567890123456', 'bank_account' => '123456'];
    }

    public function roles() { return [['customer'], ['seller']]; }
    public function methods() { return [[1], [2], [3], [4]]; }

    private function assertConflict(callable $action)
    {
        try { $action(); $this->fail('A second review must be rejected.'); }
        catch (HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
    }

    /** @dataProvider roles */
    public function testRechargeIsPendingUntilApprovedAndCannotBeCreditedTwice($role)
    {
        $user = $this->user($role); $admin = $this->user('admin');
        $service = app(WalletService::class);
        $wallet = $service->submit($this->request($this->rechargeData($role)), $user);
        $this->assertEquals(100, $user->fresh()->balance);
        $this->assertEquals(0, $wallet->approval);
        $review = $this->request(['id' => $wallet->id, 'status' => 1, 'amount' => 9999, 'remarks' => 'Bank received'], $admin);
        $service->review($review);
        $this->assertEquals(120.50, $user->fresh()->balance);
        $this->assertEquals($admin->id, $wallet->fresh()->operator_id);
        $this->assertNotNull($wallet->fresh()->reviewed_at);
        $this->assertConflict(function () use ($service, $review) { $service->review($review); });
        $this->assertConflict(function () use ($service, $admin, $wallet) { $service->review($this->request(['id' => $wallet->id, 'status' => 2], $admin)); });
        $this->assertEquals(120.50, $user->fresh()->balance);
    }

    /** @dataProvider roles */
    public function testRejectedRechargeNeverDebitsBalanceAndDuplicateTransferStaysOneRequest($role)
    {
        $user = $this->user($role); $admin = $this->user('admin'); $service = app(WalletService::class);
        $data = $this->rechargeData($role);
        $wallet = $service->submit($this->request($data), $user);
        $service->review($this->request(['id' => $wallet->id, 'status' => 2], $admin));
        $again = $service->submit($this->request($data), $user);
        $this->assertSame($wallet->id, $again->id);
        $this->assertDatabaseCount('wallets', 1);
        $this->assertEquals(100, $user->fresh()->balance);
        $this->assertConflict(function () use ($service, $admin, $wallet) { $service->review($this->request(['id' => $wallet->id, 'status' => 1], $admin)); });
    }

    public function testSellerAedConversionIgnoresTamperedHiddenUsd()
    {
        $user = $this->user('seller');
        $data = array_merge($this->rechargeData('seller'), ['amount_aed' => '367.00', 'amount' => '99999']);
        $wallet = app(WalletService::class)->submit($this->request($data), $user);
        $this->assertEquals(100, $wallet->amount);
        $this->assertStringContainsString('AED: 367.00', $wallet->payment_details);
        $this->assertEquals(100, $user->fresh()->balance);
    }

    /** @dataProvider methods */
    public function testAllWithdrawalMethodsReserveFundsAndRefundOnlyOnce($method)
    {
        $user = $this->user(); $admin = $this->user('admin');
        $user->forceFill(['cash_on_delivery_status' => 1, 'usdt_payment_status' => 1, 'usdt_type' => 'TRC20', 'usdt_address' => 'TEST-ADDRESS'])->save();
        $service = app(WithdrawalService::class);
        $withdrawal = $service->apply($this->request($this->withdrawalData($method)), $user);
        $this->assertEquals(79.50, $user->fresh()->balance);
        $this->assertEquals(2, $withdrawal->t_type);
        $this->assertNotEmpty($withdrawal->iban_details);
        $review = $this->request(['remarks' => 'Rejected'], $admin);
        $service->review($review, $withdrawal->id, false);
        $this->assertEquals(100, $user->fresh()->balance);
        $this->assertConflict(function () use ($service, $review, $withdrawal) { $service->review($review, $withdrawal->id, false); });
        $this->assertConflict(function () use ($service, $review, $withdrawal) { $service->review($review, $withdrawal->id, true); });
        $this->assertEquals(100, $user->fresh()->balance);
        $this->assertDatabaseCount('payments', 0);
    }

    /** @dataProvider methods */
    public function testPaymentConfirmationUsesOriginalAmountAndSnapshot($method)
    {
        $user = $this->user('seller'); $admin = $this->user('staff');
        $user->forceFill(['cash_on_delivery_status' => 1, 'usdt_payment_status' => 1, 'usdt_type' => 'TRC20', 'usdt_address' => 'TEST-ADDRESS'])->save();
        $service = app(WithdrawalService::class);
        $withdrawal = $service->apply($this->request($this->withdrawalData($method)), $user);
        $snapshot = $withdrawal->iban_details;
        $user->usdt_address = 'CHANGED'; $user->save();
        $review = $this->request(['amount' => 999999, 'txn_code' => 'PAYOUT-123'], $admin);
        $service->review($review, $withdrawal->id, true);
        $this->assertEquals(79.50, $user->fresh()->balance);
        $this->assertDatabaseHas('payments', ['seller_id' => $user->id, 'amount' => 20.50]);
        $details = json_decode(DB::table('payments')->value('payment_details'), true);
        $this->assertSame($snapshot, $details['account']);
        $this->assertSame('PAYOUT-123', $details['transaction_reference']);
        $this->assertEquals($admin->id, $withdrawal->fresh()->operator_id);
        $this->assertConflict(function () use ($service, $review, $withdrawal) { $service->review($review, $withdrawal->id, true); });
        $this->assertDatabaseCount('payments', 1);
    }

    /** @dataProvider roles */
    public function testDuplicateWithdrawalAndSecondPendingApplicationDoNotDebitTwice($role)
    {
        $user = $this->user($role); $service = app(WithdrawalService::class); $data = $this->withdrawalData();
        $first = $service->apply($this->request($data), $user);
        $again = $service->apply($this->request($data), $user);
        $this->assertSame($first->id, $again->id);
        try { $service->apply($this->request($this->withdrawalData()), $user); $this->fail(); }
        catch (ValidationException $e) { $this->assertArrayHasKey('amount', $e->errors()); }
        $this->assertEquals(79.50, $user->fresh()->balance);
        $this->assertDatabaseCount('seller_withdraw_requests', 1);
    }

    public function invalidAmounts() { return [['-1'], ['0'], ['1.234'], ['1e2'], ['10000000000'], ['NaN']]; }

    /** @dataProvider invalidAmounts */
    public function testInvalidAmountsCannotCreateFinancialRecords($amount)
    {
        $user = $this->user();
        foreach (['recharge', 'withdrawal'] as $kind) {
            try {
                if ($kind === 'recharge') app(WalletService::class)->submit($this->request(array_merge($this->rechargeData('customer'), ['amount' => $amount])), $user);
                else app(WithdrawalService::class)->apply($this->request(array_merge($this->withdrawalData(), ['amount' => $amount])), $user);
                $this->fail('Invalid amount accepted');
            } catch (ValidationException $e) { $this->assertArrayHasKey('amount', $e->errors()); }
        }
        $this->assertDatabaseCount('wallets', 0); $this->assertDatabaseCount('seller_withdraw_requests', 0);
        $this->assertEquals(100, $user->fresh()->balance);
    }

    public function testAnotherUsersReceiptAndUnconfiguredMethodAreRejected()
    {
        $user = $this->user();
        DB::table('uploads')->insert(['id' => 1, 'user_id' => $user->id + 1, 'type' => 'image']);
        foreach ([['photo' => 1], ['payment_option' => 'Unconfigured']] as $change) {
            try { app(WalletService::class)->submit($this->request(array_merge($this->rechargeData('customer'), $change)), $user); $this->fail(); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        $this->assertDatabaseCount('wallets', 0);
    }

    public function testSalesmanCannotReviewEitherTypeOfFunds()
    {
        $salesman = $this->user('salesman'); $request = $this->request(['id' => 1, 'status' => 1], $salesman);
        foreach (['recharge', 'withdrawal'] as $kind) {
            try {
                if ($kind === 'recharge') app(WalletService::class)->review($request);
                else app(WithdrawalService::class)->review($request, 1, true);
                $this->fail();
            } catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        }
    }

    public function testDirectAdminCreditIsValidatedAndIdempotent()
    {
        $user = $this->user('seller'); $admin = $this->user('admin'); $service = app(WalletService::class);
        $request = $this->request(['user_id' => $user->id, 'amount' => '0.01', 'request_token' => (string) Str::uuid()], $admin);
        $wallet = $service->directCredit($request); $again = $service->directCredit($request);
        $this->assertSame($wallet->id, $again->id);
        $this->assertEquals(100.01, $user->fresh()->balance);
        $this->assertDatabaseCount('wallets', 1);
    }

    public function testFailedPaymentInsertRollsBackReview()
    {
        $user = $this->user(); $admin = $this->user('admin'); $service = app(WithdrawalService::class);
        $withdrawal = $service->apply($this->request($this->withdrawalData()), $user);
        DB::statement("CREATE TRIGGER fail_payment BEFORE INSERT ON payments BEGIN SELECT RAISE(ABORT, 'test rollback'); END");
        try { $service->review($this->request([], $admin), $withdrawal->id, true); $this->fail(); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('test rollback', $e->getMessage()); }
        $this->assertEquals(0, $withdrawal->fresh()->status);
        $this->assertEquals(79.50, $user->fresh()->balance);
        $this->assertDatabaseCount('payments', 0);
    }

    public function testBuyerBankReviewModalDoesNotDependOnShopOrClientUserId()
    {
        $user = $this->user(); $admin = $this->user('admin');
        $withdrawal = app(WithdrawalService::class)->apply($this->request($this->withdrawalData(2)), $user);
        $request = $this->request(['id' => 999999, 'seller_withdraw_request_id' => $withdrawal->id], $admin);
        $controller = app(\App\Http\Controllers\SellerWithdrawRequestController::class);
        $this->assertSame($withdrawal->id, $controller->payment_modal($request)->getData()['seller_withdraw_request']->id);
        $this->assertSame($withdrawal->id, $controller->refuse_modal($request)->getData()['seller_withdraw_request']->id);
    }

    public function testBuyerRechargeModalRendersConfiguredInstructions()
    {
        $this->actingAs($this->user());
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $html = view('frontend.user.wallet.offline_recharge_modal', ['type' => 1])->render();
        $this->assertStringContainsString('Test transfer instructions', $html);
        $this->assertStringContainsString('portal=customer', $html);
    }

    public function testApiOfflineRechargeUsesTheSameValidationAndIdempotency()
    {
        $user = $this->user();
        $request = $this->request($this->rechargeData('customer'), $user);
        $controller = app(\App\Http\Controllers\Api\V2\WalletController::class);
        $this->assertTrue($controller->offline_recharge($request)->getData()->result);
        $controller->offline_recharge($request);
        $this->assertDatabaseCount('wallets', 1);
        $this->assertEquals(100, $user->fresh()->balance);
        try {
            $controller->offline_recharge($this->request(array_merge($this->rechargeData('customer'), ['amount' => '-20']), $user));
            $this->fail();
        } catch (ValidationException $e) { $this->assertArrayHasKey('amount', $e->errors()); }
    }

    public function testLegacyAppWalletCallbacksCannotCreditAnArbitraryAccount()
    {
        $user = $this->user();
        try { wallet_payment_done($user->id, '9999', 'Stripe', '{"status":"Success"}'); $this->fail(); }
        catch (HttpException $e) { $this->assertSame(422, $e->getStatusCode()); }
        $this->assertEquals(100, $user->fresh()->balance);
        $this->assertDatabaseCount('wallets', 0);
        $middleware = app(\App\Http\Middleware\RequireVerifiedWalletRecharge::class);
        foreach (['payment_type', 'value_c'] as $field) {
            $response = $middleware->handle($this->request([$field => 'wallet_payment']), function () { $this->fail('Gateway must not be called.'); });
            $this->assertSame(422, $response->status());
        }
        $this->assertSame('cart-unaffected', $middleware->handle($this->request(['payment_type' => 'cart_payment']), function () { return 'cart-unaffected'; }));
    }

    public function testBuyerWalletPaginationIsIndependentAndScoped()
    {
        $user = $this->user(); $other = $this->user(); $this->actingAs($user);
        for ($i = 0; $i < 12; $i++) {
            DB::table('wallets')->insert(['user_id' => $user->id, 'amount' => 1]);
            DB::table('seller_withdraw_requests')->insert(['user_id' => $user->id, 'amount' => 1, 'w_type' => 4, 'status' => 2, 'viewed' => 1, 'message' => '', 'remarks' => '']);
        }
        DB::table('wallets')->insert(['user_id' => $other->id, 'amount' => 9999]);
        app('request')->query->add(['rpage' => 2, 'wpage' => 1]);
        $data = app(\App\Http\Controllers\WalletController::class)->index()->getData();
        $this->assertSame(3, $data['wallets']->count());
        $this->assertSame(9, $data['seller_withdraw_requests']->count());
        $this->assertEquals(100, $data['balance']);
        $this->assertSame(12, $data['wallets']->total());
    }

    private function paypalProof($order = 'ORDER-1', $amount = '20.50')
    {
        return new \PayPalHttp\HttpResponse(201, json_decode(json_encode([
            'id' => $order, 'status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [[
                'id' => 'CAPTURE-1', 'status' => 'COMPLETED', 'amount' => ['value' => $amount, 'currency_code' => 'USD'],
            ]]]]],
        ])), []);
    }

    public function testVerifiedOnlinePaymentCreditsOnceAndRequiresBoundProviderProof()
    {
        $user = $this->user();
        DB::table('business_settings')->insert(['type' => 'paypal_payment', 'value' => '1']);
        $request = $this->request(['amount' => '20.50', 'payment_option' => 'paypal'], $user);
        $service = app(WalletService::class); $data = $service->startOnline($request);
        session(['wallet_paypal_order' => 'ORDER-1']);
        foreach (['{"status":"Success"}', $this->paypalProof('OTHER-ORDER'), $this->paypalProof('ORDER-1', '0.01')] as $proof) {
            try { $service->completeOnline($user, $data, $proof); $this->fail(); }
            catch (HttpException $e) { $this->assertSame(422, $e->getStatusCode()); }
        }
        $this->assertEquals(100, $user->fresh()->balance);
        $service->completeOnline($user, $data, $this->paypalProof());
        $service->completeOnline($user, $data, $this->paypalProof());
        $this->assertEquals(120.50, $user->fresh()->balance);
        $this->assertDatabaseCount('wallets', 1);
        $this->assertEquals(1, Wallet::first()->approval);
    }
}
