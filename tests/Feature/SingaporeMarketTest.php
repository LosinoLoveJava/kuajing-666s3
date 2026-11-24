<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SellerWithdrawRequest;
use App\Models\Wallet;
use App\Services\ExchangeRate;
use App\Services\PhoneNumber;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SingaporeMarketTest extends TestCase
{
    use \Tests\Concerns\WalletDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWalletDatabase();
        Schema::table('currencies', function (Blueprint $t) { $t->string('name')->default(''); $t->string('symbol')->default(''); $t->integer('status')->default(0); });
        Schema::create('countries', function (Blueprint $t) { $t->increments('id'); $t->string('code'); $t->string('name'); $t->integer('status'); });
        DB::table('countries')->insert([['code'=>'SG','name'=>'Singapore','status'=>1], ['code'=>'AE','name'=>'UAE','status'=>1], ['code'=>'MY','name'=>'Malaysia','status'=>1]]);
    }

    public function testMigrationIsIdempotentAndKeepsExistingUsdAndOtherCurrencies()
    {
        DB::table('currencies')->where('code','USD')->update(['exchange_rate'=>2]);
        DB::table('currencies')->insert(['code'=>'AED','exchange_rate'=>7.345]);
        DB::table('countries')->where('code','SG')->update(['status'=>0]);
        require_once database_path('migrations/2026_11_09_100000_configure_singapore_market.php');
        $migration = new \ConfigureSingaporeMarket;
        $migration->up(); $migration->up();
        $this->assertEquals(50.5, DB::table('currencies')->where('code','TRY')->value('exchange_rate'));
        $this->assertEquals(2, DB::table('currencies')->where('code','USD')->value('exchange_rate'));
        $this->assertEquals(7.345, DB::table('currencies')->where('code','AED')->value('exchange_rate'));
        $this->assertEquals(1, DB::table('countries')->where('code','SG')->value('status'));
        $this->assertSame(1, DB::table('currencies')->where('code','SGD')->count());
        $this->assertSame(['MYR'], array_keys(app(ExchangeRate::class)->availableRates()));
    }

    public function testMigrationCreatesMissingSingaporeConfiguration()
    {
        DB::table('currencies')->where('code','SGD')->delete();
        DB::table('countries')->where('code','SG')->delete();
        require_once database_path('migrations/2026_11_09_100000_configure_singapore_market.php');
        (new \ConfigureSingaporeMarket)->up();
        $this->assertEquals(50.5, DB::table('currencies')->where('code','TRY')->value('exchange_rate'));
        $this->assertSame(['MY'], array_column(app(PhoneNumber::class)->countries(), 'country'));
    }

    public function testPublicRateRejectsOldCurrencyParameters()
    {
        $this->getJson('/api/v2/exchange-rate')->assertOk()->assertJsonPath('data.rate','4.170000')->assertJsonPath('data.to','MYR');
        $this->getJson('/api/v2/exchange-rate?to=AED')->assertStatus(422);
        $this->getJson('/api/v2/exchange-rate?to=SGD')->assertStatus(422);
    }

    public function testSingaporePhoneIsUnavailableEvenIfItsCountryRecordIsEnabled()
    {
        foreach (['65123456', '6565123456', '+65 6512 3456', '006565123456'] as $number) {
            foreach (['SG', null] as $country) {
                try { app(PhoneNumber::class)->normalize($number, $country); $this->fail('Retired Singapore phone accepted.'); }
                catch (ValidationException $error) { $this->assertArrayHasKey('phone', $error->errors()); }
            }
        }
        $this->getJson('/api/v2/auth/phone/countries')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.country', 'MY');
        $this->postJson('/api/v2/auth/phone/send-code', ['phone'=>'+6581234567', 'country'=>'SG', 'purpose'=>'register'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');
        // Existing staff authentication is independent of the public country selector.
        $this->assertSame('+971501234567', app(PhoneNumber::class)->normalizeAdmin('+971 50 123 4567'));
        $this->assertSame('+6581234567', app(PhoneNumber::class)->normalizeAdmin('81234567'));
    }

    public function testRetiredRechargeFieldsCannotUseAHiddenUsdFallback()
    {
        $seller = new User;
        $seller->forceFill(['name'=>'Seller', 'user_type'=>'seller'])->save();
        foreach ([['currency'=>'SGD','amount_local'=>'128'], ['amount_sgd'=>'128'], ['amount_sgd'=>'']] as $retired) {
            $payload = array_merge(['amount'=>'100', 'recharge_method'=>'IBAN', 'request_token'=>(string) Str::uuid()], $retired);
            try { app(WalletService::class)->submit(Request::create('/', 'POST', $payload), $seller); $this->fail('SGD recharge accepted.'); }
            catch (ValidationException $error) { $this->assertArrayHasKey(isset($retired['currency']) ? 'currency' : 'amount_sgd', $error->errors()); }
        }
        $this->assertDatabaseCount('wallets', 0);
        $this->assertEquals(100, $seller->fresh()->balance);
    }

    public function testOldRechargeCurrencyCannotFallBackToAHiddenUsdAmount()
    {
        $seller = new User;
        $seller->forceFill(['name'=>'Seller', 'user_type'=>'seller'])->save();
        $request = Request::create('/offline-wallet-recharge', 'POST', ['amount_aed'=>'367', 'amount'=>'100', 'recharge_method'=>'IBAN', 'request_token'=>(string) Str::uuid()]);
        $this->expectException(ValidationException::class);
        try { app(WalletService::class)->submit($request, $seller); }
        finally { $this->assertDatabaseCount('wallets', 0); }
    }

    public function testCustomerPaymentOptionCannotBeOverriddenByASellerField()
    {
        $customer = new User;
        $customer->forceFill(['name'=>'Buyer', 'user_type'=>'customer'])->save();
        $request = Request::create('/offline-wallet-recharge', 'POST', ['amount'=>'1', 'trx_id'=>'REF-1', 'payment_option'=>'Test bank transfer', 'recharge_method'=>'IBAN']);
        $wallet = app(WalletService::class)->submit($request, $customer);
        $this->assertSame('Test bank transfer', $wallet->payment_method);
        $this->assertEquals(0, $wallet->approval);
        $this->assertEquals(100, $customer->fresh()->balance);
    }

    public function testHistoricalSingaporeRecordsRemainReviewableWithTheirOriginalSnapshots()
    {
        $seller = new User; $seller->forceFill(['name'=>'Seller', 'user_type'=>'seller', 'balance'=>'80.00'])->save();
        $admin = new User; $admin->forceFill(['name'=>'Admin', 'user_type'=>'admin'])->save();
        $snapshot = ['country'=>'SG', 'market'=>'SG', 'currency'=>'SGD', 'account_number'=>'00123456',
            'exchange_rate'=>'1.280000', 'estimated_amount'=>'25.60'];
        $withdrawal = new SellerWithdrawRequest;
        $withdrawal->forceFill(['user_id'=>$seller->id, 'amount'=>'20.00', 'w_type'=>4, 'status'=>0,
            'viewed'=>0, 'message'=>'Historical request', 'remarks'=>'', 'iban_details'=>$snapshot])->save();
        $review = Request::create('/', 'POST'); $review->setUserResolver(function () use ($admin) { return $admin; });
        app(WithdrawalService::class)->review($review, $withdrawal->id, false);
        $this->assertEquals(100, $seller->fresh()->balance);
        $this->assertSame($snapshot, $withdrawal->fresh()->iban_details);

        $wallet = new Wallet;
        $wallet->forceFill(['user_id'=>$seller->id, 'amount'=>'100.04', 'payment_method'=>'IBAN',
            'payment_details'=>'SGD: 128.05 (1 USD = 1.280000 SGD)', 'offline_payment'=>1, 'approval'=>0])->save();
        $review->merge(['id'=>$wallet->id, 'status'=>1]);
        app(WalletService::class)->review($review);
        $this->assertEquals(200.04, $seller->fresh()->balance);
        $this->assertSame('SGD: 128.05 (1 USD = 1.280000 SGD)', $wallet->fresh()->payment_details);
    }
}
