<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerPhoneRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:',
            'cache.default'=>'array', 'session.driver'=>'array']);
        DB::purge('sqlite');
        Http::fake();
        Notification::fake();
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('email')->nullable();
            $t->string('phone')->nullable(); $t->string('phone_normalized')->nullable()->unique();
            $t->timestamp('phone_verified_at')->nullable(); $t->timestamp('email_verified_at')->nullable();
            $t->string('password'); $t->string('user_type')->default('customer');
            $t->boolean('banned')->default(false); $t->integer('reputation_score')->default(100);
            $t->string('remember_token')->nullable(); $t->integer('pid')->default(0); $t->integer('referred_by')->nullable();
            foreach (['tpwd','usdt_address','node','deep','identity_card_front','identity_card_back','certtype'] as $column) { $t->string($column)->default(''); }
            $t->timestamps();
        });
        Schema::create('shops', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id')->unique(); $t->string('name'); $t->string('address');
            $t->string('phone'); $t->string('slug'); $t->integer('seller_package_id'); $t->integer('product_upload_limit');
            $t->date('package_invalid_at'); $t->integer('verification_status')->default(0);
            $t->integer('seller_spread_package_id'); $t->integer('product_spread_limit');
            $t->decimal('bzj_money',20,2); $t->string('usdt_address'); $t->integer('views_up_time'); $t->timestamps();
        });
        Schema::create('seller_packages', function (Blueprint $t) {
            $t->increments('id'); $t->integer('is_default'); $t->integer('duration'); $t->integer('product_upload_limit'); $t->timestamps();
        });
        DB::table('seller_packages')->insert(['id'=>1,'is_default'=>1,'duration'=>30,'product_upload_limit'=>100]);
        Schema::create('seller_package_payments', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->integer('seller_package_id'); $t->string('payment_method');
            $t->string('payment_details'); $t->integer('approval'); $t->integer('offline_payment'); $t->timestamps();
        });
        Schema::create('uploads', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->string('type'); $t->timestamps();
        });
        DB::table('uploads')->insert([
            ['id'=>1,'user_id'=>0,'type'=>'image'], ['id'=>2,'user_id'=>0,'type'=>'image'],
            ['id'=>3,'user_id'=>0,'type'=>'image'], ['id'=>4,'user_id'=>999,'type'=>'image'],
            ['id'=>5,'user_id'=>0,'type'=>'document'],
        ]);
        Schema::create('phone_verifications', function (Blueprint $t) {
            $t->increments('id'); $t->string('phone')->unique(); $t->string('purpose'); $t->string('code_hash')->nullable();
            $t->integer('attempts')->default(0); $t->timestamp('expires_at')->nullable(); $t->timestamps();
        });
        Schema::create('countries', function (Blueprint $t) { $t->increments('id'); $t->string('code'); $t->string('name'); $t->integer('status'); });
        DB::table('countries')->insert([
            ['code'=>'AE','name'=>'United Arab Emirates','status'=>1], ['code'=>'GB','name'=>'United Kingdom','status'=>0],
        ]);
        Schema::create('translations', function (Blueprint $t) { $t->increments('id'); $t->string('lang'); $t->string('lang_key'); $t->text('lang_value'); $t->timestamps(); });
        $this->seedCode();
    }

    private function seedCode($purpose = 'register', $expired = false)
    {
        DB::table('phone_verifications')->updateOrInsert(['phone'=>'+971501234567'], [
            'purpose'=>$purpose, 'code_hash'=>Hash::make('123456'), 'attempts'=>0,
            'expires_at'=>$expired ? now()->subSecond() : now()->addMinutes(5),
        ]);
    }

    private function payload($overrides = [])
    {
        return array_merge(['mode'=>'register','phone'=>'+971 50 123 4567','country'=>'AE','code'=>'123456',
            'account_name'=>'Test Merchant','name'=>'Test Shop','address'=>'Dubai',
            'password'=>'password123','password_confirmation'=>'password123', 'certtype'=>'idcard',
            'identity_card_front'=>1,'identity_card_back'=>2], $overrides);
    }

    public function testApplicationCreatesVerifiedSellerShopAndDefaultPackageAtomically()
    {
        $this->post('/shops', $this->payload())->assertRedirect(route('shops.index'));
        $user = User::firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('seller', $user->user_type);
        $this->assertSame('Test Merchant', $user->name);
        $this->assertSame('+971501234567', $user->phone_normalized);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertNull($user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertDatabaseHas('shops', ['user_id'=>$user->id,'name'=>'Test Shop','phone'=>$user->phone,
            'verification_status'=>0,'seller_package_id'=>1,'product_upload_limit'=>100,'package_invalid_at'=>now()->addDays(30)->toDateString()]);
        $this->assertDatabaseHas('seller_package_payments', ['user_id'=>$user->id,'payment_method'=>'free','approval'=>1]);
        $this->assertDatabaseHas('uploads', ['id'=>1,'user_id'=>$user->id]);
        $this->assertDatabaseHas('uploads', ['id'=>2,'user_id'=>$user->id]);
        $this->assertDatabaseHas('uploads', ['id'=>3,'user_id'=>0]);
        $this->assertNull(DB::table('phone_verifications')->value('code_hash'));
        Notification::assertNothingSent(); Http::assertNothingSent();
    }

    public function testGenericCodeCreatesTheSameVerifiedSellerAndShopWithoutAnSmsRecord()
    {
        $this->post('/shops', $this->payload(['code'=>'89001']))->assertRedirect(route('shops.index'));
        $user = User::firstOrFail();
        $this->assertSame('seller', $user->user_type);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertDatabaseHas('shops', ['user_id'=>$user->id, 'name'=>'Test Shop']);
        $this->assertDatabaseHas('seller_package_payments', ['user_id'=>$user->id, 'payment_method'=>'free']);
        // The fixture contains a normal SMS record; generic-code validation
        // must leave it untouched and must not consume or rewrite it.
        $this->assertTrue(Hash::check('123456', DB::table('phone_verifications')->value('code_hash')));
        Http::assertNothingSent();
    }

    public function testNewSellerCanLogInWithPhonePasswordAndSms()
    {
        $this->post('/shops', $this->payload())->assertRedirect();
        $user = User::firstOrFail();
        auth()->logout();
        $this->post('/users/phone', ['mode'=>'login','phone'=>$user->phone,'password'=>'password123'])
            ->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticatedAs($user);
        auth()->logout();
        $this->post('/login', ['country_code'=>'971','phone'=>'501234567','password'=>'password123'])
            ->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticatedAs($user);
        auth()->logout();
        $this->seedCode('login');
        $this->post('/users/phone', ['mode'=>'login','phone'=>$user->phone,'code'=>'123456'])
            ->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('shops', 1);
    }

    public function testMerchantInvitationIsRetained()
    {
        $parent = new User;
        $parent->name = 'Sales Referrer'; $parent->password = Hash::make('password123');
        $parent->user_type = 'salesman'; $parent->save();
        $this->withUnencryptedCookie('invitation_code', (string) $parent->id)->post('/shops', $this->payload())->assertRedirect();
        $this->assertDatabaseHas('users', ['phone_normalized'=>'+971501234567','user_type'=>'seller','pid'=>$parent->id]);
    }

    public function testSellerCodeCannotBeMissingExpiredOrForAnotherPurpose()
    {
        $this->postJson('/shops', $this->payload(['code'=>'']))->assertStatus(422)->assertJsonValidationErrors('code');
        $this->seedCode('login');
        $this->postJson('/shops', $this->payload())->assertStatus(422)->assertJsonValidationErrors('code');
        $this->seedCode('register', true);
        $this->postJson('/shops', $this->payload())->assertStatus(422)->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('users', 0); $this->assertDatabaseCount('shops', 0);
    }

    public function testWrongCodeAttemptsAreRetainedAndBlockFurtherRegistration()
    {
        for ($i=0; $i<5; $i++) {
            $this->postJson('/shops', $this->payload(['code'=>'000000']))->assertStatus(422);
        }
        $this->assertSame(5, (int) DB::table('phone_verifications')->value('attempts'));
        $this->postJson('/shops', $this->payload())->assertStatus(422);
        $this->assertDatabaseCount('users', 0); $this->assertDatabaseCount('shops', 0);
    }

    public function testDuplicatePhoneAndCodeReuseCannotCreateAnotherShop()
    {
        $this->post('/shops', $this->payload())->assertRedirect();
        auth()->logout();
        DB::table('uploads')->insert(['id'=>6,'user_id'=>0,'type'=>'image']);
        $documents = ['identity_card_front'=>3,'identity_card_back'=>6];
        $this->postJson('/shops', $this->payload(array_merge($documents, ['phone'=>'+971501234568'])))
            ->assertStatus(422)->assertJsonValidationErrors('code');
        $this->seedCode();
        $this->postJson('/shops', $this->payload($documents))->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->assertDatabaseCount('users', 1); $this->assertDatabaseCount('shops', 1);
    }

    public function testInvalidShopDetailsAndDocumentsDoNotConsumeCode()
    {
        foreach ([['identity_card_front'=>null], ['identity_card_front'=>4], ['identity_card_back'=>5],
            ['account_name'=>''], ['address'=>''], ['password_confirmation'=>'other-password']] as $case) {
            $this->postJson('/shops', $this->payload($case))->assertStatus(422);
        }
        $this->assertDatabaseCount('users', 0); $this->assertDatabaseCount('shops', 0);
        $this->assertTrue(Hash::check('123456', DB::table('phone_verifications')->value('code_hash')));
    }

    public function testMissingDefaultPackageDoesNotCreateAnOrphanAccount()
    {
        DB::table('seller_packages')->delete();
        $this->postJson('/shops', $this->payload())->assertStatus(422);
        $this->assertDatabaseCount('users', 0); $this->assertDatabaseCount('shops', 0);
        $this->assertNotNull(DB::table('phone_verifications')->value('code_hash'));
    }

    public function testShopFailureRollsBackAccountDocumentsPackageAndCodeConsumption()
    {
        Shop::creating(function () { throw new \RuntimeException('Simulated shop failure.'); });
        $this->postJson('/shops', $this->payload())->assertStatus(500);
        $this->assertDatabaseCount('users', 0); $this->assertDatabaseCount('shops', 0);
        $this->assertDatabaseCount('seller_package_payments', 0);
        $this->assertDatabaseHas('uploads', ['id'=>1,'user_id'=>0]);
        $this->assertNotNull(DB::table('phone_verifications')->value('code_hash'));
    }

    public function testDisabledCountryCannotBeUsedForSellerRegistration()
    {
        $this->postJson('/shops', $this->payload(['country'=>'GB','phone'=>'+447911123456']))
            ->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->assertDatabaseCount('users', 0);
    }

    public function testOpeningApplicationRetainsAnonymousDocumentsAndUsesEnabledCountries()
    {
        $view = app(\App\Http\Controllers\ShopController::class)->create(Request::create('/shops/create'));
        $this->assertSame('frontend.seller_form', $view->name());
        $this->assertSame(['AE'], array_column($view->getData()['countries'], 'country'));
        $this->assertDatabaseCount('uploads', 5);
    }

    public function testSellerPhoneRegistrationLinkOpensTheFullShopApplication()
    {
        $this->get('https://seller.pddglobalmerchanthub.com/users/phone?mode=register')
            ->assertRedirect('https://seller.pddglobalmerchanthub.com/shops/create');
    }

    public function testMerchantLoginAndResetUseTheSameNationalPhoneInput()
    {
        $finder = app('view')->getFinder();
        $finder->prependLocation(base_path('tests/Fixtures/views'));
        $finder->flush();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        app('request')->setLaravelSession(app('session.store'));
        foreach (['login','reset_password'] as $mode) {
            $request = Request::create('https://seller.pddglobalmerchanthub.com/users/phone?mode='.$mode);
            $html = app(\App\Http\Controllers\PhoneAccessController::class)->show($request)->render();
            $this->assertStringContainsString('value="AE" data-dial-code="+971"  selected', $html);
            $this->assertStringContainsString('name="national_phone" value=""', $html);
            $this->assertStringContainsString('type="hidden" name="phone"', $html);
            $this->assertStringContainsString('assets/js/intlTelutils.js', $html);
            $this->assertStringNotContainsString('International phone number', $html);
        }
    }

    public function testShopSubmissionRequiresCsrfOutsideTheTestingBypass()
    {
        app()->instance('env', 'production');
        try {
            $this->postJson('/shops', $this->payload())->assertStatus(419);
            $this->withSession(['_token'=>'test-csrf-token'])->postJson('/shops', $this->payload(['_token'=>'test-csrf-token','code'=>'000000']))
                ->assertStatus(422)->assertJsonValidationErrors('code');
        } finally { app()->instance('env', 'testing'); }
        $this->assertDatabaseCount('users', 0);
    }
}
