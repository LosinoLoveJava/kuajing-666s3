<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SellerBalanceDeductionTest extends TestCase
{
    use \Tests\Concerns\WalletDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWalletDatabase();
        $this->withoutMiddleware();
        foreach ([1=>'admin', 2=>'staff', 3=>'seller', 4=>'salesman', 5=>'customer', 6=>'seller'] as $id=>$role) {
            $user = new User;
            $user->forceFill(['id'=>$id, 'name'=>'Test '.$role, 'user_type'=>$role, 'balance'=>'100.00'])->save();
        }
        app()->setLocale('en');
        app('view')->getFinder()->prependLocation(base_path('tests/Fixtures/views'));
        app('view')->getFinder()->flush();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    }

    private function payload(array $changes = [])
    {
        return array_merge(['amount'=>'20.50', 'remarks'=>'Account adjustment', 'request_token'=>(string) Str::uuid()], $changes);
    }

    public function operators() { return [[1], [2]]; }

    /** @dataProvider operators */
    public function testDeductionRecordsAuditAndDuplicateSubmissionDoesNotDebitAgain($operator)
    {
        $this->actingAs(User::findOrFail($operator));
        $data = $this->payload(); $url = route('sellers.balance_deduction.store', 3);
        $this->post($url, $data)->assertRedirect(route('sellers.balance_deduction', 3))->assertSessionHas('status');
        $this->post($url, $data)->assertRedirect(route('sellers.balance_deduction', 3));
        $this->assertEquals(79.50, User::findOrFail(3)->balance);
        $this->assertEquals(100, User::findOrFail(6)->balance);
        $this->assertDatabaseCount('wallets', 1);
        $entry = DB::table('wallets')->first();
        $this->assertEquals(-20.50, $entry->amount);
        $this->assertSame(WalletService::ADMIN_DEBIT, $entry->payment_method);
        $this->assertEquals($operator, $entry->operator_id);
        $this->assertEquals(1, $entry->approval);
        $this->assertEquals(0, $entry->offline_payment);
        $this->assertSame('Account adjustment', $entry->remarks);
        $this->assertNotNull($entry->reviewed_at);
        $this->assertSame(['currency'=>'USD', 'balance_before'=>'100.00', 'balance_after'=>'79.50'], json_decode($entry->payment_details, true));
    }

    public function invalidInputs()
    {
        return [[['amount'=>'0'],'amount'], [['amount'=>'-1'],'amount'], [['amount'=>'1e2'],'amount'],
            [['amount'=>'1.234'],'amount'], [['amount'=>'10000000000'],'amount'], [['amount'=>'100.01'],'amount'],
            [['remarks'=>''],'remarks'], [['remarks'=>str_repeat('x',2001)],'remarks'],
            [['request_token'=>''],'request_token'], [['request_token'=>'not-a-uuid'],'request_token']];
    }

    /** @dataProvider invalidInputs */
    public function testInvalidInputAndInsufficientFundsLeaveBalanceAndLedgerUntouched($changes, $field)
    {
        $this->actingAs(User::findOrFail(1))->postJson(route('sellers.balance_deduction.store', 3), $this->payload($changes))
            ->assertStatus(422)->assertJsonValidationErrors($field);
        $this->assertEquals(100, User::findOrFail(3)->balance);
        $this->assertDatabaseCount('wallets', 0);
    }

    public function testWholeBalanceCanBeDeductedAndStaleBalanceCannotOverdraw()
    {
        $this->actingAs(User::findOrFail(1));
        $url = route('sellers.balance_deduction.store', 3);
        $this->post($url, $this->payload(['amount'=>'99.99']))->assertRedirect();
        $last = $this->payload(['amount'=>'0.01']);
        $this->post($url, $last)->assertRedirect();
        $this->post($url, $last)->assertRedirect();
        $this->postJson($url, $this->payload(['amount'=>'0.01']))->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->assertEquals(0, User::findOrFail(3)->balance);
        $this->assertDatabaseCount('wallets', 2);
    }

    public function deniedOperators() { return [[null], [3], [4], [5]]; }

    /** @dataProvider deniedOperators */
    public function testOtherRolesCannotReadOrSubmitDeductions($operator)
    {
        if ($operator) $this->actingAs(User::findOrFail($operator));
        $this->getJson(route('sellers.balance_deduction', 3))->assertForbidden();
        $this->postJson(route('sellers.balance_deduction.store', 3), $this->payload())->assertForbidden();
        $this->assertEquals(100, User::findOrFail(3)->balance);
        $this->assertDatabaseCount('wallets', 0);
    }

    public function testBannedOperatorAndNonSellerTargetsAreRejected()
    {
        $admin = User::findOrFail(1); $admin->banned = 1; $admin->save();
        $this->actingAs($admin)->postJson(route('sellers.balance_deduction.store', 3), $this->payload())->assertForbidden();
        $this->getJson(route('sellers.balance_deduction', 3))->assertForbidden();
        $admin->banned = 0; $admin->save(); $this->actingAs($admin);
        foreach ([1, 5, 999] as $target) {
            $this->postJson(route('sellers.balance_deduction.store', $target), $this->payload())->assertNotFound();
        }
        $this->assertDatabaseCount('wallets', 0);
    }

    public function testReusedTokenCannotChangeAmountReasonOrSeller()
    {
        $this->actingAs(User::findOrFail(1));
        $data = $this->payload();
        $this->post(route('sellers.balance_deduction.store', 3), $data)->assertRedirect();
        foreach ([['amount'=>'1'], ['remarks'=>'Changed reason']] as $change) {
            $this->postJson(route('sellers.balance_deduction.store', 3), array_merge($data,$change))->assertStatus(409);
        }
        $this->postJson(route('sellers.balance_deduction.store', 6), $data)->assertStatus(409);
        $this->assertEquals(79.5, User::findOrFail(3)->balance);
        $this->assertEquals(100, User::findOrFail(6)->balance);
        $this->assertDatabaseCount('wallets', 1);
    }

    public function testFailedBalanceWriteRollsBackLedger()
    {
        DB::statement("CREATE TRIGGER fail_debit BEFORE UPDATE OF balance ON users BEGIN SELECT RAISE(ABORT, 'test rollback'); END");
        $request = Request::create('/', 'POST', $this->payload());
        $request->setUserResolver(function () { return User::findOrFail(1); });
        try { app(WalletService::class)->debitSeller($request, 3); $this->fail('Expected balance write failure'); }
        catch (\Illuminate\Database\QueryException $error) { $this->assertStringContainsString('test rollback', $error->getMessage()); }
        $this->assertEquals(100, User::findOrFail(3)->balance);
        $this->assertDatabaseCount('wallets', 0);
    }

    public function testHistoryOnlyShowsThisSellerAndEscapesReasons()
    {
        $this->actingAs(User::findOrFail(1));
        $this->post(route('sellers.balance_deduction.store', 3), $this->payload(['remarks'=>'<script>alert(1)</script>']))->assertRedirect();
        $this->post(route('sellers.balance_deduction.store', 6), $this->payload(['remarks'=>'Other seller reason']))->assertRedirect();
        $this->get(route('sellers.balance_deduction', 3))->assertOk()->assertSee('Balance deduction')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('Other seller reason')->assertSee('79.50')->assertSee('100.00');
    }

    public function testDeductionCannotBeApprovedAsRechargeAndPostRouteRequiresCsrf()
    {
        $this->actingAs(User::findOrFail(1));
        $this->post(route('sellers.balance_deduction.store', 3), $this->payload())->assertRedirect();
        $request = Request::create('/', 'POST', ['id'=>DB::table('wallets')->value('id'), 'status'=>1]);
        $request->setUserResolver(function () { return User::findOrFail(1); });
        try { app(WalletService::class)->review($request); $this->fail('Debit reviewed as recharge'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { $this->assertSame(409, $error->getStatusCode()); }
        $route = app('router')->getRoutes()->getByName('sellers.balance_deduction.store');
        $this->assertSame(['POST'], $route->methods());
        $this->assertContains(\App\Http\Middleware\VerifyCsrfToken::class, $route->gatherMiddleware());
        $this->assertEquals(79.50, User::findOrFail(3)->balance);
    }

    public function testAppRechargeHistoryDoesNotMislabelDeductionAsRecharge()
    {
        $this->actingAs(User::findOrFail(1));
        $this->post(route('sellers.balance_deduction.store', 3), $this->payload())->assertRedirect();
        $this->actingAs(User::findOrFail(3));
        $controller = app(\App\Http\Controllers\Api\V2\WalletController::class);
        $this->assertCount(0, $controller->walletRechargeHistory()->collection);
        session(['currency_symbol'=>'$']);
        $this->assertSame('Not Available', $controller->balance()->getData()->last_recharged);
    }
}
