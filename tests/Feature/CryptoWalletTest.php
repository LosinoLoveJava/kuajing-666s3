<?php

namespace Tests\Feature;

use App\Http\Controllers\CryptoWalletSettingController;
use App\Models\User;
use App\Services\CryptoWallet;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CryptoWalletTest extends TestCase
{
    use \Tests\Concerns\WalletDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWalletDatabase();
        $settings = [];
        foreach (self::assets() as [$code, $address, $price]) {
            $settings[$code] = ['enabled' => true, 'address' => $address, 'usd_price' => $price];
        }
        DB::table('business_settings')->insert(['type' => CryptoWallet::SETTING, 'value' => json_encode($settings)]);
    }

    public function assets()
    {
        return [
            ['USDT_TRC20', 'T'.str_repeat('A', 33), '1'],
            ['USDT_ERC20', '0x'.str_repeat('a', 40), '1'],
            ['BTC', '1BoatSLRHtKNngkdXEeobR76b53LETtpyT', '65000'],
            ['TRX', 'T'.str_repeat('B', 33), '0.25'],
        ];
    }

    private function user($role = 'seller')
    {
        $user = new User;
        $user->forceFill(['name' => 'Test '.$role, 'user_type' => $role, 'balance' => '100.00'])->save();
        return $user;
    }

    private function request($data, $user = null)
    {
        $request = Request::create('/', 'POST', $data);
        if ($user) $request->setUserResolver(function () use ($user) { return $user; });
        return $request;
    }

    /** @dataProvider assets */
    public function testDepositUsesServerPriceSnapshotAndOnlyCreditsOnceAfterApproval($code, $address, $price)
    {
        DB::table('manual_payment_methods')->delete();
        $user = $this->user();
        $request = $this->request(['recharge_method' => 'Crypto', 'crypto_asset' => $code,
            'crypto_amount' => '0.12345678', 'amount' => '999999', 'trx_id' => str_repeat('a', 64)]);
        $wallet = app(WalletService::class)->submit($request, $user);
        $this->assertSame(CryptoWallet::toUsd('0.12345678', $price), number_format($wallet->amount, 2, '.', ''));
        $this->assertEquals(100, $user->fresh()->balance);
        $this->assertEquals(0, $wallet->approval);
        $this->assertStringContainsString($address, $wallet->payment_details);
        $this->assertSame($wallet->id, app(WalletService::class)->submit($request, $user)->id);
        DB::table('business_settings')->where('type', CryptoWallet::SETTING)->update(['value' => '{}']);
        app(WalletService::class)->review($this->request(['id' => $wallet->id, 'status' => 1], $this->user('admin')));
        $this->assertEquals(bcadd('100', (string) $wallet->amount, 2), $user->fresh()->balance);
        $this->assertStringContainsString($address, $wallet->fresh()->payment_details);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(WalletService::class)->review($this->request(['id' => $wallet->id, 'status' => 1], $this->user('admin')));
    }

    /** @dataProvider assets */
    public function testWithdrawalReservesBalanceSnapshotsQuoteAndRefundsOnce($code, $address, $price)
    {
        $user = $this->user();
        $request = $this->request(['w_type' => 5, 'amount' => '20.50', 'crypto_asset' => $code,
            'crypto_address' => $address, 'withdrawal_market' => 'MY', 'request_token' => (string) Str::uuid()]);
        $withdrawal = app(WithdrawalService::class)->apply($request, $user);
        $this->assertSame(0, bccomp('79.50', (string) $user->fresh()->balance, 2));
        $this->assertSame($address, $withdrawal->iban_details['address']);
        $this->assertSame(CryptoWallet::fromUsd('20.50', $price), $withdrawal->iban_details['estimated_amount']);
        $this->assertSame($withdrawal->id, app(WithdrawalService::class)->apply($request, $user)->id);
        DB::table('business_settings')->where('type', CryptoWallet::SETTING)->update(['value' => '{}']);
        app(WithdrawalService::class)->review($this->request([], $this->user('admin')), $withdrawal->id, false);
        $this->assertEquals(100, $user->fresh()->balance);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(WithdrawalService::class)->review($this->request([], $this->user('admin')), $withdrawal->id, false);
    }

    public function testApprovedWithdrawalCreatesPaymentWithOriginalRecipient()
    {
        [$code, $address] = self::assets()[0];
        $user = $this->user();
        $record = app(WithdrawalService::class)->apply($this->request(['w_type' => 5, 'amount' => '10', 'crypto_asset' => $code, 'crypto_address' => $address]), $user);
        app(WithdrawalService::class)->review($this->request(['txn_code' => 'TEST-TRANSFER'], $this->user('admin')), $record->id, true);
        $this->assertEquals(90, $user->fresh()->balance);
        $this->assertSame('USDT TRC20', DB::table('payments')->value('payment_method'));
        $this->assertStringContainsString($address, DB::table('payments')->value('payment_details'));
    }

    public function testDuplicateTransactionCannotBeUsedByAnotherAccountOrTronAsset()
    {
        $data = ['recharge_method' => 'Crypto', 'crypto_asset' => 'USDT_TRC20', 'crypto_amount' => '10', 'trx_id' => str_repeat('b', 64)];
        app(WalletService::class)->submit($this->request($data), $this->user());
        $data['crypto_asset'] = 'TRX';
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(WalletService::class)->submit($this->request($data), $this->user());
    }

    public function badDeposits()
    {
        return [['crypto_amount', '-1'], ['crypto_amount', '1e3'], ['crypto_amount', '0'], ['crypto_amount', '0.000000001'],
            ['crypto_amount', '9999999999'], ['trx_id', 'INVALID'], ['crypto_asset', 'ETH']];
    }

    /** @dataProvider badDeposits */
    public function testInvalidDepositsNeverCreateRecords($field, $value)
    {
        $data = ['recharge_method' => 'Crypto', 'crypto_asset' => 'BTC', 'crypto_amount' => '1', 'trx_id' => str_repeat('c', 64)];
        $data[$field] = $value;
        try { app(WalletService::class)->submit($this->request($data), $this->user()); $this->fail('Invalid deposit accepted'); }
        catch (ValidationException $error) { $this->assertDatabaseCount('wallets', 0); }
    }

    public function testWrongNetworkAddressAndInsufficientBalanceAreRejected()
    {
        $user = $this->user();
        foreach ([['crypto_address' => '0x'.str_repeat('a', 40)], ['amount' => '101']] as $change) {
            $data = array_merge(['w_type' => 5, 'amount' => '10', 'crypto_asset' => 'TRX', 'crypto_address' => 'T'.str_repeat('A', 33)], $change);
            try { app(WithdrawalService::class)->apply($this->request($data), $user); $this->fail('Invalid withdrawal accepted'); }
            catch (ValidationException $error) { $this->assertDatabaseCount('seller_withdraw_requests', 0); $this->assertEquals(100, $user->fresh()->balance); }
        }
    }

    public function testUnconfiguredAssetsCannotAcceptMoney()
    {
        DB::table('business_settings')->delete();
        $this->assertFalse(CryptoWallet::assets()['TRX']['enabled']);
        $this->expectException(ValidationException::class);
        app(WalletService::class)->submit($this->request(['recharge_method' => 'Crypto', 'crypto_asset' => 'TRX', 'crypto_amount' => '10', 'trx_id' => str_repeat('a', 64)]), $this->user());
    }

    public function testAdminCanSaveAllNetworksAndOtherRolesCannot()
    {
        DB::table('business_settings')->delete();
        $settings = [];
        foreach (self::assets() as [$code, $address, $price]) $settings[$code] = ['enabled' => 1, 'address' => $address, 'usd_price' => $price];
        app(CryptoWalletSettingController::class)->update($this->request(['assets' => $settings], $this->user('admin')));
        $this->assertTrue(CryptoWallet::assets()['BTC']['deposit_enabled']);
        foreach (['seller', 'customer', 'staff', 'salesman'] as $role) {
            try { app(CryptoWalletSettingController::class)->update($this->request(['assets' => $settings], $this->user($role))); $this->fail('Unauthorized settings update'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { $this->assertSame(403, $error->getStatusCode()); }
        }
    }

    public function testAdminCannotEnableInvalidAddressOrZeroRate()
    {
        $settings = [];
        foreach (self::assets() as [$code, $address, $price]) $settings[$code] = ['enabled' => 1, 'address' => $address, 'usd_price' => $price];
        foreach (['address' => 'INVALID', 'usd_price' => '0'] as $field => $value) {
            $invalid = $settings;
            $invalid['TRX'][$field] = $value;
            try { app(CryptoWalletSettingController::class)->update($this->request(['assets' => $invalid], $this->user('admin'))); $this->fail('Invalid configuration saved'); }
            catch (ValidationException $error) { $this->assertArrayHasKey('assets.TRX.'.$field, $error->errors()); }
        }
    }
}
