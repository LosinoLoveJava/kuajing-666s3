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

class MalaysiaMarketTest extends TestCase
{
    use \Tests\Concerns\WalletDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWalletDatabase();
        Schema::table('currencies', function (Blueprint $t) { $t->string('name')->default(''); $t->string('symbol')->default(''); $t->integer('status')->default(1); });
        Schema::create('countries', function (Blueprint $t) { $t->increments('id'); $t->string('code'); $t->string('name'); $t->integer('status'); });
        DB::table('countries')->insert(['code'=>'SG','name'=>'Singapore','status'=>1]);
        require_once database_path('migrations/2026_11_23_100000_configure_malaysia_market.php');
        (new \ConfigureMalaysiaMarket)->up();
    }

    private function seller()
    {
        $user = new User;
        $user->forceFill(['name'=>'Test Seller','user_type'=>'seller','balance'=>'1000.00'])->save();
        return $user;
    }

    public function testMigrationCreatesMissingMalaysiaConfigurationAndKeepsCustomLabels()
    {
        DB::table('currencies')->where('code','MYR')->delete();
        DB::table('countries')->where('code','MY')->delete();
        (new \ConfigureMalaysiaMarket)->up();
        $this->assertSame('4.170000', app(ExchangeRate::class)->rate());
        $this->assertSame(['MY'], array_column(app(PhoneNumber::class)->countries(), 'country'));
        $this->assertDatabaseHas('translations', ['lang'=>'cn','lang_key'=>'account_holder','lang_value'=>'持户人姓名']);
        DB::table('translations')->where('lang','cn')->where('lang_key','account_holder')->update(['lang_value'=>'自定义持户人']);
        (new \ConfigureMalaysiaMarket)->up();
        $this->assertSame(1, DB::table('currencies')->where('code','MYR')->count());
        $this->assertDatabaseHas('translations', ['lang'=>'cn','lang_key'=>'account_holder','lang_value'=>'自定义持户人']);
    }

    public function testConfiguredMyrRateUsesUsdBaseAndLeavesSingaporeUntouched()
    {
        DB::table('currencies')->where('code','USD')->update(['exchange_rate'=>2]);
        (new \ConfigureMalaysiaMarket)->up(); (new \ConfigureMalaysiaMarket)->up();
        $this->assertSame('4.170000', app(ExchangeRate::class)->rate('MYR'));
        $this->assertEquals(1.28, DB::table('currencies')->where('code','SGD')->value('exchange_rate'));
        $this->assertSame(1, DB::table('currencies')->where('code','MYR')->count());
        $this->getJson('/api/v2/exchange-rate?from=USD&to=MYR')->assertOk()->assertJsonPath('data.rate','4.170000')->assertJsonPath('data.to','MYR');
        $this->getJson('/api/v2/exchange-rate?to=EUR')->assertStatus(422);
    }

    /** @dataProvider malaysianNumbers */
    public function testMalaysianPhoneFormatsNormalizeToOneAccount($value, $country)
    {
        $this->assertSame('+60123456789', app(PhoneNumber::class)->normalize($value, $country));
    }

    public function malaysianNumbers()
    {
        return [['123456789','MY'], ['012-345 6789','MY'], ['+60 (12) 345-6789','MY'],
            ['0060123456789','MY'], ['60123456789','MY'], ['+60123456789',null], ['60123456789',null]];
    }

    public function testLongMobilePrefixesAndLocalTrunkPrefix()
    {
        foreach (['011-1234 5678', '1112345678', '+601112345678', '00601112345678', '601112345678'] as $number) {
            $this->assertSame('+601112345678', app(PhoneNumber::class)->normalize($number));
        }
        foreach (['015-1234 5678', '+60 15 1234 5678'] as $number) {
            $this->assertSame('+601512345678', app(PhoneNumber::class)->normalize($number));
        }
    }

    public function testCountryMismatchNonMobileAndDisabledMalaysiaAreRejected()
    {
        foreach ([['+60123456789','SG'],['+6581234567','MY'],['2121234567','MY'],['532123456','MY'],['00123456789','MY']] as $case) {
            try { app(PhoneNumber::class)->normalize(...$case); $this->fail('Invalid phone accepted'); }
            catch (ValidationException $error) { $this->assertArrayHasKey('phone', $error->errors()); }
        }
        DB::table('countries')->where('code','MY')->update(['status'=>0]);
        $this->assertCount(0, app(PhoneNumber::class)->countries());
        $this->expectException(ValidationException::class);
        app(PhoneNumber::class)->normalize('+60123456789');
    }

    /** @dataProvider localAmounts */
    public function testRechargeConvertsSelectedCurrencyOnServerAndKeepsManualReview($currency, $local, $usd)
    {
        $seller = $this->seller();
        $request = Request::create('/', 'POST', ['currency'=>$currency,'amount_local'=>$local,'amount'=>'999999',
            'recharge_method'=>'Bank','bank_name'=>'Test Bank','account_holder'=>'Test Holder','bank_account'=>'00123456','request_token'=>(string) Str::uuid()]);
        $wallet = app(WalletService::class)->submit($request,$seller);
        $this->assertEquals($usd, $wallet->amount);
        $this->assertEquals(0, $wallet->approval);
        $this->assertEquals(1000, $seller->fresh()->balance);
        $this->assertStringContainsString($currency.': '.$local, $wallet->payment_details);
        $this->assertSame($wallet->id, app(WalletService::class)->submit($request,$seller)->id);
    }

    public function localAmounts()
    {
        return [['MYR','417.00','100.00'],['MYR','4.19','1.00'],['MYR','4.20','1.01'],
            ['MYR','0.03','0.01'],['MYR','9999999999.99','2398081534.77']];
    }

    /** @dataProvider invalidRecharges */
    public function testInvalidOrAmbiguousCurrencyCannotCreditUsdFallback($overrides, $field)
    {
        $request = Request::create('/', 'POST', array_merge(['currency'=>'MYR','amount_local'=>'417','amount'=>'100',
            'recharge_method'=>'Bank','bank_name'=>'Test Bank','account_holder'=>'Test Holder','bank_account'=>'00123456','request_token'=>(string) Str::uuid()], $overrides));
        try { app(WalletService::class)->submit($request,$this->seller()); $this->fail('Invalid recharge accepted'); }
        catch (ValidationException $error) { $this->assertArrayHasKey($field, $error->errors()); }
        $this->assertDatabaseCount('wallets',0);
    }

    public function invalidRecharges()
    {
        return [[['bank_name'=>''],'bank_name'], [['account_holder'=>''],'account_holder'], [['bank_account'=>''],'bank_account'],
            [['currency'=>'TRY'],'currency'], [['currency'=>'EUR'],'currency'], [['currency'=>null],'currency'], [['amount_local'=>''],'amount_local'],
            [['amount_local'=>'0'],'amount_local'], [['amount_local'=>'0.01'],'amount_local'],
            [['amount_local'=>'-1'],'amount_local'], [['amount_local'=>'1.234'],'amount_local'],
            [['amount_sgd'=>'128'],'amount_sgd'], [['amount_aed'=>'367'],'amount_aed']];
    }

    private function malaysiaWithdrawal($overrides = [])
    {
        return Request::create('/', 'POST', array_merge(['withdrawal_market'=>'MY','amount'=>'20.50','w_type'=>4,
            'iban_country'=>'MY','iban_holder'=>'Test Holder','iban_bank'=>'Test Bank','bank_account'=>'00123456',
            'request_token'=>(string) Str::uuid()], $overrides));
    }

    public function testMalaysiaWithdrawalSavesRecipientAndRateSnapshotAndRefundsExactlyOnce()
    {
        $seller = $this->seller(); $service = app(WithdrawalService::class); $request = $this->malaysiaWithdrawal();
        $withdrawal = $service->apply($request,$seller);
        $this->assertEquals(979.5, $seller->fresh()->balance);
        $this->assertSame('00123456', $withdrawal->iban_details['account_number']);
        $this->assertSame('MYR', $withdrawal->iban_details['currency']);
        $this->assertSame('85.49', $withdrawal->iban_details['estimated_amount']);
        $this->assertSame($withdrawal->id, $service->apply($request,$seller)->id);
        DB::table('currencies')->where('code','MYR')->update(['exchange_rate'=>60]);
        $this->assertSame('4.170000', $withdrawal->fresh()->iban_details['exchange_rate']);
        $review = Request::create('/', 'POST');
        $admin = new User; $admin->forceFill(['name'=>'Admin','user_type'=>'admin'])->save();
        $review->setUserResolver(function () use ($admin) { return $admin; });
        $service->review($review,$withdrawal->id,false);
        $this->assertEquals(1000, $seller->fresh()->balance);
        try { $service->review($review,$withdrawal->id,false); $this->fail('Second refund accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { $this->assertSame(409,$error->getStatusCode()); }
    }

    /** @dataProvider invalidWithdrawals */
    public function testMalaysiaRequiresRecipientAndEnabledMarket($overrides, $field)
    {
        $seller = $this->seller();
        try { app(WithdrawalService::class)->apply($this->malaysiaWithdrawal($overrides),$seller); $this->fail('Invalid withdrawal accepted'); }
        catch (ValidationException $error) { $this->assertArrayHasKey($field,$error->errors()); }
        $this->assertEquals(1000,$seller->fresh()->balance);
        $this->assertDatabaseCount('seller_withdraw_requests',0);
    }

    public function invalidWithdrawals()
    {
        return [[['bank_account'=>''],'bank_account'],[['iban_holder'=>''],'iban_holder'],[['iban_bank'=>''],'iban_bank'],[['w_type'=>2],'w_type'],
            [['withdrawal_market'=>'TR'],'withdrawal_market'], [['iban_country'=>'TR'],'iban_country'],
            [['withdrawal_market'=>'SG'],'withdrawal_market'], [['withdrawal_market'=>null],'withdrawal_market'],
            [['withdrawal_market'=>null,'iban_country'=>'SG'],'withdrawal_market']];
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

    public function testMissingMyrRateBlocksBankingWithoutSingaporeFallback()
    {
        DB::table('currencies')->where('code','MYR')->delete();
        $this->getJson('/api/v2/exchange-rate?to=MYR')->assertStatus(503);
        $this->getJson('/api/v2/exchange-rate')->assertStatus(503);
        $this->assertNull(app(ExchangeRate::class)->availableRates()['MYR']);
        $this->expectException(ValidationException::class);
        app(WithdrawalService::class)->apply($this->malaysiaWithdrawal(),$this->seller());
    }

    public function testEmptyLegacySgdCannotFallBackToHiddenUsd()
    {
        $this->expectException(ValidationException::class);
        app(WalletService::class)->submit(Request::create('/', 'POST', ['amount_sgd'=>'','amount'=>'100',
            'recharge_method'=>'Bank','bank_name'=>'Test Bank','account_holder'=>'Test Holder','bank_account'=>'00123456','request_token'=>(string) Str::uuid()]),$this->seller());
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
