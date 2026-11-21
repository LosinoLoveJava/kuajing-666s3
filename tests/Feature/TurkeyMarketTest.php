<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
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

class TurkeyMarketTest extends TestCase
{
    use \Tests\Concerns\WalletDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWalletDatabase();
        Schema::table('currencies', function (Blueprint $t) { $t->string('name')->default(''); $t->string('symbol')->default(''); $t->integer('status')->default(1); });
        Schema::create('countries', function (Blueprint $t) { $t->increments('id'); $t->string('code'); $t->string('name'); $t->integer('status'); });
        DB::table('countries')->insert(['code'=>'SG','name'=>'Singapore','status'=>1]);
        require_once database_path('migrations/2026_11_16_100000_add_turkey_market.php');
        (new \AddTurkeyMarket)->up();
    }

    private function seller()
    {
        $user = new User;
        $user->forceFill(['name'=>'Test Seller','user_type'=>'seller','balance'=>'1000.00'])->save();
        return $user;
    }

    public function testConfiguredTryRateUsesUsdBaseAndLeavesSingaporeUntouched()
    {
        DB::table('currencies')->where('code','USD')->update(['exchange_rate'=>2]);
        (new \AddTurkeyMarket)->up(); (new \AddTurkeyMarket)->up();
        $this->assertSame('50.500000', app(ExchangeRate::class)->rate('TRY'));
        $this->assertEquals(1.28, DB::table('currencies')->where('code','SGD')->value('exchange_rate'));
        $this->assertSame(1, DB::table('currencies')->where('code','TRY')->count());
        $this->getJson('/api/v2/exchange-rate?from=USD&to=TRY')->assertOk()->assertJsonPath('data.rate','50.500000')->assertJsonPath('data.to','TRY');
        $this->getJson('/api/v2/exchange-rate?to=EUR')->assertStatus(422);
    }

    /** @dataProvider turkishNumbers */
    public function testTurkishPhoneFormatsNormalizeToOneAccount($value, $country)
    {
        $this->assertSame('+905321234567', app(PhoneNumber::class)->normalize($value, $country));
    }

    public function turkishNumbers()
    {
        return [['5321234567','TR'], ['0532 123 45 67','TR'], ['+90 (532) 123-45-67','TR'],
            ['00905321234567','TR'], ['905321234567','TR'], ['+905321234567',null], ['905321234567',null]];
    }

    public function testCountryMismatchNonMobileAndDisabledTurkeyAreRejected()
    {
        foreach ([['+905321234567','SG'],['+6581234567','TR'],['2121234567','TR'],['532123456','TR'],['005321234567','TR']] as $case) {
            try { app(PhoneNumber::class)->normalize(...$case); $this->fail('Invalid phone accepted'); }
            catch (ValidationException $error) { $this->assertArrayHasKey('phone', $error->errors()); }
        }
        DB::table('countries')->where('code','TR')->update(['status'=>0]);
        $this->assertCount(0, app(PhoneNumber::class)->countries());
        $this->expectException(ValidationException::class);
        app(PhoneNumber::class)->normalize('+905321234567');
    }

    /** @dataProvider localAmounts */
    public function testRechargeConvertsSelectedCurrencyOnServerAndKeepsManualReview($currency, $local, $usd)
    {
        $seller = $this->seller();
        $request = Request::create('/', 'POST', ['currency'=>$currency,'amount_local'=>$local,'amount'=>'999999',
            'recharge_method'=>'IBAN','request_token'=>(string) Str::uuid()]);
        $wallet = app(WalletService::class)->submit($request,$seller);
        $this->assertEquals($usd, $wallet->amount);
        $this->assertEquals(0, $wallet->approval);
        $this->assertEquals(1000, $seller->fresh()->balance);
        $this->assertStringContainsString($currency.': '.$local, $wallet->payment_details);
        $this->assertSame($wallet->id, app(WalletService::class)->submit($request,$seller)->id);
    }

    public function localAmounts()
    {
        return [['TRY','5050.00','100.00'],['TRY','50.75','1.00'],['TRY','50.76','1.01'],
            ['TRY','0.51','0.01'],['TRY','9999999999.99','198019801.98']];
    }

    /** @dataProvider invalidRecharges */
    public function testInvalidOrAmbiguousCurrencyCannotCreditUsdFallback($overrides, $field)
    {
        $request = Request::create('/', 'POST', array_merge(['currency'=>'TRY','amount_local'=>'5050','amount'=>'100',
            'recharge_method'=>'IBAN','request_token'=>(string) Str::uuid()], $overrides));
        try { app(WalletService::class)->submit($request,$this->seller()); $this->fail('Invalid recharge accepted'); }
        catch (ValidationException $error) { $this->assertArrayHasKey($field, $error->errors()); }
        $this->assertDatabaseCount('wallets',0);
    }

    public function invalidRecharges()
    {
        return [[['currency'=>'EUR'],'currency'], [['currency'=>null],'currency'], [['amount_local'=>''],'amount_local'],
            [['amount_local'=>'0'],'amount_local'], [['amount_local'=>'0.01'],'amount_local'],
            [['amount_local'=>'-1'],'amount_local'], [['amount_local'=>'1.234'],'amount_local'],
            [['amount_sgd'=>'128'],'amount_sgd'], [['amount_aed'=>'367'],'amount_aed']];
    }

    private function turkeyWithdrawal($overrides = [])
    {
        return Request::create('/', 'POST', array_merge(['withdrawal_market'=>'TR','amount'=>'20.50','w_type'=>4,
            'iban_country'=>'TR','iban_holder'=>'Test Holder','iban_bank'=>'Test Bank','iban_number'=>'TR33 0006 1005 1978 6457 8413 26',
            'request_token'=>(string) Str::uuid()], $overrides));
    }

    public function testTurkeyWithdrawalSavesRecipientAndRateSnapshotAndRefundsExactlyOnce()
    {
        $seller = $this->seller(); $service = app(WithdrawalService::class); $request = $this->turkeyWithdrawal();
        $withdrawal = $service->apply($request,$seller);
        $this->assertEquals(979.5, $seller->fresh()->balance);
        $this->assertSame('TR330006100519786457841326', $withdrawal->iban_details['iban']);
        $this->assertSame('TRY', $withdrawal->iban_details['currency']);
        $this->assertSame('1035.25', $withdrawal->iban_details['estimated_amount']);
        $this->assertSame($withdrawal->id, $service->apply($request,$seller)->id);
        DB::table('currencies')->where('code','TRY')->update(['exchange_rate'=>60]);
        $this->assertSame('50.500000', $withdrawal->fresh()->iban_details['exchange_rate']);
        $review = Request::create('/', 'POST');
        $admin = new User; $admin->forceFill(['name'=>'Admin','user_type'=>'admin'])->save();
        $review->setUserResolver(function () use ($admin) { return $admin; });
        $service->review($review,$withdrawal->id,false);
        $this->assertEquals(1000, $seller->fresh()->balance);
        try { $service->review($review,$withdrawal->id,false); $this->fail('Second refund accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { $this->assertSame(409,$error->getStatusCode()); }
    }

    /** @dataProvider invalidWithdrawals */
    public function testTurkeyRequiresFullRecipientAndTurkishIban($overrides, $field)
    {
        $seller = $this->seller();
        try { app(WithdrawalService::class)->apply($this->turkeyWithdrawal($overrides),$seller); $this->fail('Invalid withdrawal accepted'); }
        catch (ValidationException $error) { $this->assertArrayHasKey($field,$error->errors()); }
        $this->assertEquals(1000,$seller->fresh()->balance);
        $this->assertDatabaseCount('seller_withdraw_requests',0);
    }

    public function invalidWithdrawals()
    {
        return [[['iban_holder'=>''],'iban_holder'],[['iban_bank'=>''],'iban_bank'],[['iban_number'=>''],'iban_number'],
            [['iban_number'=>'TR330006100519786457841327'],'iban_number'],[['iban_country'=>'AE'],'iban_country'],[['w_type'=>2],'w_type'],
            [['withdrawal_market'=>'SG'],'withdrawal_market'], [['withdrawal_market'=>null],'withdrawal_market'],
            [['withdrawal_market'=>null,'iban_country'=>'SG'],'iban_country']];
    }

    public function testSingaporeWithdrawalIsRejectedWithoutReservingFunds()
    {
        $seller = $this->seller();
        try {
            app(WithdrawalService::class)->apply(Request::create('/', 'POST', ['withdrawal_market'=>'SG','iban_country'=>'SG','amount'=>'10','w_type'=>4]),$seller);
            $this->fail('Retired Singapore withdrawal accepted');
        } catch (ValidationException $error) { $this->assertArrayHasKey('withdrawal_market', $error->errors()); }
        $this->assertEquals(1000, $seller->fresh()->balance);
        $this->assertDatabaseCount('seller_withdraw_requests', 0);
    }

    public function testMissingTryRateBlocksBankingWithoutSingaporeFallback()
    {
        DB::table('currencies')->where('code','TRY')->delete();
        $this->getJson('/api/v2/exchange-rate?to=TRY')->assertStatus(503);
        $this->getJson('/api/v2/exchange-rate')->assertStatus(503);
        $this->assertNull(app(ExchangeRate::class)->availableRates()['TRY']);
        $this->expectException(ValidationException::class);
        app(WithdrawalService::class)->apply($this->turkeyWithdrawal(),$this->seller());
    }

    public function testEmptyLegacySgdCannotFallBackToHiddenUsd()
    {
        $this->expectException(ValidationException::class);
        app(WalletService::class)->submit(Request::create('/', 'POST', ['amount_sgd'=>'','amount'=>'100',
            'recharge_method'=>'IBAN','request_token'=>(string) Str::uuid()]),$this->seller());
    }

    public function testTranslatedProductNameAndUnitRetainOriginalDescriptionFallback()
    {
        $product = new Product(['name'=>'Original','description'=>'<p>Details</p>','unit'=>'piece']);
        $product->setRelation('product_translations',collect([(object)['lang'=>'tr','name'=>'Ürün','description'=>null,'unit'=>null]]));
        $this->assertSame('Ürün',$product->getTranslation('name','tr'));
        $this->assertSame('<p>Details</p>',$product->getTranslation('description','tr'));
        $this->assertSame('Adet',$product->getTranslation('unit','tr'));
    }

    public function testLanguageMigrationTranslatesCatalogAndPreservesLaterAdministratorEdits()
    {
        Schema::create('languages', function (Blueprint $t) {
            $t->increments('id'); $t->string('code'); $t->string('name'); $t->string('app_lang_code');
            $t->integer('rtl')->default(0); $t->integer('status')->default(1); $t->timestamps();
        });
        foreach (['categories','products'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->increments('id'); $t->string('name'); });
        }
        foreach (['category_translations'=>'category_id','product_translations'=>'product_id'] as $table=>$foreignKey) {
            Schema::create($table, function (Blueprint $t) use ($foreignKey) {
                $t->increments('id'); $t->integer($foreignKey); $t->string('lang'); $t->string('name')->nullable(); $t->timestamps();
            });
        }
        $pack = json_decode(file_get_contents(database_path('data/turkish_market.json')),true);
        $category = array_key_first($pack['categories']); $product = array_key_first($pack['products']);
        DB::table('categories')->insert(['id'=>1,'name'=>$category]);
        DB::table('products')->insert([['id'=>1,'name'=>$product],['id'=>2,'name'=>strtoupper($product)]]);
        require_once database_path('migrations/2026_11_16_110000_add_turkish_language_and_catalog.php');
        $migration = new \AddTurkishLanguageAndCatalog;
        $migration->up();
        $this->assertDatabaseHas('languages',['code'=>'tr','status'=>1,'rtl'=>0]);
        $this->assertDatabaseHas('product_translations',['product_id'=>1,'lang'=>'tr','name'=>$pack['products'][$product]]);
        $this->assertDatabaseHas('product_translations',['product_id'=>2,'lang'=>'tr','name'=>$pack['products'][$product]]);
        $this->assertDatabaseHas('category_translations',['category_id'=>1,'lang'=>'tr','name'=>$pack['categories'][$category]]);
        DB::table('translations')->where('lang','tr')->where('lang_key','products')->update(['lang_value'=>'Custom product heading']);
        DB::table('product_translations')->where('lang','tr')->update(['name'=>'Custom Turkish title']);
        $migration->up();
        $this->assertDatabaseCount('languages',1);
        $this->assertDatabaseCount('product_translations',2);
        $this->assertDatabaseCount('category_translations',1);
        $this->assertSame('Custom product heading', DB::table('translations')->where('lang','tr')->where('lang_key','products')->value('lang_value'));
        $this->assertSame('Custom Turkish title', DB::table('product_translations')->value('name'));
    }
}
