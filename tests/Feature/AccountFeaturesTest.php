<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SellerWithdrawRequest;
use App\Services\Iban;
use App\Services\PhoneNumber;
use App\Services\PhoneVerification;
use App\Services\WithdrawalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountFeaturesTest extends TestCase
{
    use \Tests\Concerns\InteractsWithPortalSessions;

    private $code;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array',
            'chuanx.appkey' => 'test-key', 'chuanx.appcode' => 'test-code', 'chuanx.appsecret' => 'test-secret']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $t) {
            $t->dateTime('last_login_at')->nullable();
            $t->increments('id'); $t->string('name'); $t->string('email')->nullable();
            $t->string('phone')->nullable(); $t->string('password')->nullable();
            $t->string('user_type')->default('customer'); $t->boolean('banned')->default(false);
            $t->timestamp('email_verified_at')->nullable(); $t->string('verification_code')->nullable();
            $t->string('remember_token')->nullable(); $t->decimal('balance', 20, 2)->default(0);
            foreach (['tpwd','usdt_address','node','deep','referral_code'] as $column) { $t->string($column)->default(''); }
            $t->timestamps();
        });
        Schema::create('seller_withdraw_requests', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->decimal('amount',20,2);
            $t->text('message'); $t->integer('status'); $t->integer('viewed');
            $t->integer('type')->default(1); $t->integer('w_type'); $t->integer('t_type')->default(1);
            $t->text('remarks')->default(''); $t->timestamps();
        });
        require_once database_path('migrations/2026_10_20_160000_add_account_features.php');
        (new \AddAccountFeatures)->up();
        Schema::table('seller_withdraw_requests', function (Blueprint $t) {
            $t->string('request_key', 64)->nullable()->unique();
            $t->integer('operator_id')->nullable(); $t->timestamp('reviewed_at')->nullable();
        });
        Schema::create('countries', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('code'); $t->integer('status'); });
        DB::table('countries')->insert([
            ['name'=>'Turkey','code'=>'TR','status'=>1], ['name'=>'United Kingdom','code'=>'GB','status'=>0],
            ['name'=>'United States','code'=>'US','status'=>1], ['name'=>'Bahamas','code'=>'BS','status'=>0],
        ]);
        Schema::create('currencies', function (Blueprint $t) { $t->increments('id'); $t->string('code'); $t->decimal('exchange_rate',10,5); $t->timestamps(); });
        Schema::create('orders', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id'); $t->integer('seller_id'); $t->string('payment_status'); $t->string('delivery_status'); });
        Schema::create('order_details', function (Blueprint $t) { $t->increments('id'); $t->integer('order_id'); });
        Schema::create('refund_requests', function (Blueprint $t) { $t->increments('id'); $t->integer('order_detail_id'); $t->integer('refund_status'); });
        Schema::create('shops', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id'); $t->decimal('bzj_money',20,2); $t->timestamps(); });
        Schema::create('payments', function (Blueprint $t) { $t->increments('id'); $t->integer('seller_id'); $t->decimal('amount',20,2); $t->string('payment_method'); $t->text('payment_details')->nullable(); $t->integer('t_type')->default(1); $t->timestamps(); });
        Schema::create('personal_access_tokens', function (Blueprint $t) { $t->bigIncrements('id'); $t->morphs('tokenable'); $t->string('name'); $t->string('token',64)->unique(); $t->text('abilities')->nullable(); $t->timestamp('last_used_at')->nullable(); $t->timestamps(); });
        Schema::create('uploads', function (Blueprint $t) { $t->increments('id'); });
        Schema::create('translations', function (Blueprint $t) { $t->increments('id'); $t->string('lang'); $t->string('lang_key'); $t->text('lang_value'); $t->timestamps(); });
        Http::fake(function ($request) {
            preg_match('/code is ([0-9]{6})/', $request['msg'], $match);
            $this->code = $match[1];
            return Http::response(['code'=>'00000','uid'=>str_repeat('a',32),'result'=>[['phone'=>$request['phone'],'status'=>'00000']]]);
        });
    }

    private function renderPhonePage($mode)
    {
        $finder = app('view')->getFinder();
        $paths = $finder->getPaths();
        $finder->prependLocation(base_path('tests/Fixtures/views'));
        $finder->flush();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        app('request')->setLaravelSession(app('session.store'));
        try {
            return app(\App\Http\Controllers\PhoneAccessController::class)
                ->show(Request::create('/users/phone?mode='.$mode))->render();
        } finally {
            $finder->setPaths($paths);
            $finder->flush();
        }
    }

    public function testAllPublicPhoneModesOfferEnabledCountrySelector()
    {
        foreach (['register', 'login', 'reset_password'] as $mode) {
            $html = $this->renderPhonePage($mode);
            $this->assertStringContainsString('name="country"', $html);
            $this->assertStringContainsString('Turkey +90', $html);
            $this->assertStringContainsString('name="national_phone" value=""', $html);
            $this->assertStringContainsString('autocomplete="tel-national"', $html);
            $this->assertStringContainsString('<select class="form-control', $html);
            $this->assertStringNotContainsString('International phone number', $html);
            $this->assertStringContainsString('assets/js/intlTelutils.js', $html);
        }
    }

    public function testOldCountryInputCannotChangeFixedTurkeyCountry()
    {
        session()->flashInput(['country'=>'US', 'phone'=>'+12025550123', 'national_phone'=>'+12025550123']);
        $html = $this->renderPhonePage('register');
        $this->assertStringContainsString('value="TR"  selected', $html);
        $this->assertStringNotContainsString('value="US"', $html);
        // Keep invalid old input visible for correction; never silently turn a foreign account into Turkey.
        $this->assertStringContainsString('name="national_phone" value="+12025550123"', $html);
    }

    public function testDisabledTurkeyCannotBeUsedEvenWithFixedPrefix()
    {
        DB::table('countries')->where('code', 'TR')->update(['status'=>0]);
        $this->getJson('/api/v2/auth/phone/countries')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v2/auth/phone/send-code', ['phone'=>'+905321234567', 'purpose'=>'register'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');
        Http::assertNothingSent();
    }

    /** @dataProvider turkeyNumberFormats */
    public function testTurkeyNumberFormatsNormalizeToOneAccount($number)
    {
        $this->assertSame('+905321234567', app(PhoneNumber::class)->normalize($number));
    }

    public function turkeyNumberFormats(): array
    {
        return [['5321234567'], ['5321234567'], ['+90 532 123 4567'], ['00905321234567'], ['905321234567']];
    }

    /** @dataProvider forbiddenPhoneInputs */
    public function testFixedPrefixRejectsOtherCountriesAndInvalidNumbersBeforeSms($phone, $country)
    {
        $this->postJson('/api/v2/auth/phone/send-code', ['phone'=>$phone, 'country'=>$country, 'purpose'=>'register'])
            ->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->assertDatabaseCount('phone_verifications', 0);
        Http::assertNothingSent();
    }

    public function forbiddenPhoneInputs(): array
    {
        return [['+12025550123','US'], ['+12025550123',null], ['+447911123456',null],
            ['+905321234567','US'], ['+971',null], ['abc',null], ['501234',null]];
    }

    public function testLocalTurkeyNumberCanRegisterAndLogInViaApi()
    {
        $payload = ['phone'=>'5321234567','name'=>'Turkey Customer','code'=>'89001',
            'password'=>'password123','password_confirmation'=>'password123'];
        $this->postJson('/api/v2/auth/phone/register', $payload)->assertOk();
        $this->assertDatabaseHas('users', ['phone'=>'+905321234567', 'phone_normalized'=>'+905321234567']);
        $this->postJson('/api/v2/auth/phone/login', ['phone'=>'5321234567','password'=>'password123'])->assertOk();
        Http::assertNothingSent();
    }

    public function testTurkishSmsRegisterLoginAndResetUseNormalizedNumberAndSingleUseCode()
    {
        DB::table('countries')->where('code','TR')->update(['status'=>1]);
        $this->getJson('/api/v2/auth/phone/countries')->assertOk()->assertJsonCount(1,'data')->assertJsonFragment(['country'=>'TR','dial_code'=>'+90','name'=>'Turkey']);
        $this->postJson('/api/v2/auth/phone/send-code',['phone'=>'0532 123 45 67','country'=>'TR','purpose'=>'register'])->assertOk();
        Http::assertSent(function ($request) { return $request['phone'] === '905321234567'; });
        $payload = ['phone'=>'5321234567','country'=>'TR','name'=>'Turkish Customer','code'=>$this->code,
            'password'=>'password123','password_confirmation'=>'password123'];
        $this->postJson('/api/v2/auth/phone/register',$payload)->assertOk();
        $this->assertDatabaseHas('users',['phone'=>'+905321234567','phone_normalized'=>'+905321234567']);
        $this->postJson('/api/v2/auth/phone/login',['phone'=>'+905321234567','password'=>'password123'])->assertOk();
        $this->travel(61)->seconds();
        $this->postJson('/api/v2/auth/phone/send-code',['phone'=>'+905321234567','purpose'=>'reset_password'])->assertOk();
        $payload['code'] = $this->code; $payload['password'] = $payload['password_confirmation'] = 'newpassword123';
        $this->postJson('/api/v2/auth/phone/reset-password',$payload)->assertOk();
        $this->postJson('/api/v2/auth/phone/reset-password',$payload)->assertStatus(422)->assertJsonValidationErrors('code');
        $this->postJson('/api/v2/auth/phone/login',['phone'=>'00905321234567','password'=>'newpassword123'])->assertOk();
    }

    public function testLegacyLoginAndCartDefaultToFixedTurkeyWithoutCountryCode()
    {
        $user = $this->user(); $user->phone_verified_at = now(); $user->save();
        $response = $this->post('/login', ['phone'=>'5321234567','password'=>'password123'])->assertRedirect(route('dashboard', ['portal' => 'customer']));
        $this->assertPortalAuthenticatedAs($user, $response); auth()->logout();
        $this->post(route('cart.login.submit'), ['phone'=>'5321234567','password'=>'password123'])->assertRedirect();
        $this->assertAuthenticatedAs($user); auth()->logout();
        $this->postJson('/login', ['phone'=>'5321234567','country_code'=>'1','password'=>'password123'])
            ->assertStatus(422)->assertJsonValidationErrors('country_code');
    }

    private function user($role = 'customer', $phone = '+905321234567')
    {
        $user = new User;
        $user->name = 'Test User'; $user->user_type = $role; $user->phone = $phone;
        $user->password = Hash::make('password123'); $user->balance = '100.00';
        $user->save();
        return $user;
    }

    private function seedCode($phone, $purpose, $attempts = 0, $expired = false)
    {
        DB::table('phone_verifications')->updateOrInsert(['phone'=>$phone], [
            'purpose'=>$purpose,'code_hash'=>Hash::make('123456'),'attempts'=>$attempts,
            'expires_at'=>$expired ? now()->subSecond() : now()->addMinutes(5),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function ibanRequest($overrides = [])
    {
        return Request::create('/withdraw', 'POST', array_merge([
            'amount'=>'20.50','type'=>1,'w_type'=>4,'iban_country'=>'TR','iban_holder'=>'Test Holder',
            'iban_bank'=>'Test Bank','iban_number'=>'TR33 0006 1005 1978 6457 8413 26','iban_bic'=>'BOMLAEAD',
        ], $overrides));
    }

    public function testExchangeRateUsesRelativeConfiguredRates()
    {
        DB::table('currencies')->insert([['code'=>'USD','exchange_rate'=>'2'],['code'=>'TRY','exchange_rate'=>'101.0']]);
        $this->getJson('/api/v2/exchange-rate?from=USD&to=TRY')->assertOk()->assertJsonPath('data.rate','50.500000');
    }

    public function testMissingAndZeroRatesDoNotInventARate()
    {
        $this->getJson('/api/v2/exchange-rate')->assertStatus(503);
        DB::table('currencies')->insert([['code'=>'USD','exchange_rate'=>0],['code'=>'TRY','exchange_rate'=>50.5]]);
        $this->getJson('/api/v2/exchange-rate')->assertStatus(503);
        $this->getJson('/api/v2/exchange-rate?from=EUR')->assertStatus(422);
    }

    public function testPrivateMetricsRequireLogin()
    {
        $this->getJson('/api/v2/user/completed-orders/count')->assertUnauthorized();
        $this->getJson('/api/v2/user/reputation')->assertUnauthorized();
    }

    public function testOrdersCountBuyerSellerAndRefundBoundaries()
    {
        $buyer=$this->user(); $seller=$this->user('seller','+905321234568'); $other=$this->user('customer','+905321234569');
        for($id=1;$id<=6;$id++) {
            DB::table('orders')->insert(['id'=>$id,'user_id'=>$id===6?$other->id:$buyer->id,'seller_id'=>$seller->id,
                'payment_status'=>$id===4?'unpaid':'paid','delivery_status'=>$id===5?'pending':'delivered']);
            DB::table('order_details')->insert([['id'=>$id*2,'order_id'=>$id],['id'=>$id*2+1,'order_id'=>$id]]);
        }
        DB::table('refund_requests')->insert([['order_detail_id'=>4,'refund_status'=>1],['order_detail_id'=>5,'refund_status'=>1],['order_detail_id'=>6,'refund_status'=>1],['order_detail_id'=>3,'refund_status'=>0]]);
        $this->actingAs($buyer,'sanctum')->getJson('/api/v2/user/completed-orders/count?user_id='.$other->id)->assertOk()->assertJsonPath('data.count',2);
        $this->actingAs($seller,'sanctum')->getJson('/api/v2/user/completed-orders/count')->assertOk()->assertJsonPath('data.count',3);
    }

    public function testDefaultScoreAndAdminAdjustmentAudit()
    {
        $user=$this->user(); $admin=$this->user('admin','+905321234568');
        $this->actingAs($user,'sanctum')->getJson('/api/v2/user/reputation')->assertOk()->assertJsonPath('data.score',100);
        $this->actingAs($admin)->post('/admin/reputation/'.$user->id,['score'=>80,'reason'=>'Verified adjustment'])->assertRedirect();
        $this->assertSame(80,(int)$user->fresh()->reputation_score);
        $this->assertDatabaseHas('reputation_logs',['user_id'=>$user->id,'old_score'=>100,'new_score'=>80,'operator_id'=>$admin->id]);
    }

    public function testScoreRejectsUnauthorizedOrInvalidChanges()
    {
        $user=$this->user(); $staff=$this->user('staff','+905321234568');
        $this->actingAs($staff)->postJson('/admin/reputation/'.$user->id,['score'=>50,'reason'=>'test'])->assertForbidden();
        $admin=$this->user('admin','+905321234569');
        $this->actingAs($admin)->postJson('/admin/reputation/'.$user->id,['score'=>101,'reason'=>''])->assertStatus(422);
        $this->assertDatabaseCount('reputation_logs',0);
    }

    public function testIbanValidationAndImmutableSnapshot()
    {
        $this->assertTrue(Iban::valid('TR33 0006 1005 1978 6457 8413 26','TR'));
        $this->assertTrue(Iban::valid('GB82 WEST 1234 5698 7654 32','GB'));
        $this->assertFalse(Iban::valid('AE08 0331 2345 6789 0123 456','AE'));
        $this->assertFalse(Iban::valid('GB82 WEST 1234 5698 7654 32','AE'));
        $user=$this->user();
        $withdrawal=app(WithdrawalService::class)->apply($this->ibanRequest(),$user,true);
        $this->assertSame('TR330006100519786457841326',$withdrawal->fresh()->iban_details['iban']);
        $user->name='Changed'; $user->save();
        $this->assertSame('Test Holder',$withdrawal->fresh()->iban_details['holder']);
        $this->assertEquals(79.50,$user->fresh()->balance);
    }

    public function testInvalidIbanDoesNotDebitBalance()
    {
        $user=$this->user();
        try { app(WithdrawalService::class)->apply($this->ibanRequest(['iban_number'=>'invalid']),$user,true); $this->fail(); }
        catch(ValidationException $e) { $this->assertArrayHasKey('iban_number',$e->errors()); }
        $this->assertEquals(100,$user->fresh()->balance);
        $this->assertDatabaseCount('seller_withdraw_requests',0);
    }

    public function testWithdrawalRejectsNegativeAndExcessAmounts()
    {
        $user=$this->user();
        foreach(['-1','0','100.01','1.234'] as $amount) {
            try { app(WithdrawalService::class)->apply($this->ibanRequest(['amount'=>$amount]),$user,true); $this->fail(); }
            catch(ValidationException $e) { $this->assertArrayHasKey('amount',$e->errors()); }
        }
        $this->assertEquals(100,$user->fresh()->balance);
    }

    public function testRetiredDepositWithdrawalAndExistingWalletMethods()
    {
        $seller=$this->user('seller');
        DB::table('shops')->insert(['user_id'=>$seller->id,'bzj_money'=>50]);
        try { app(WithdrawalService::class)->apply($this->ibanRequest(['type'=>2]),$seller); $this->fail(); }
        catch (ValidationException $e) { $this->assertArrayHasKey('type', $e->errors()); }
        $this->assertEquals(50,DB::table('shops')->value('bzj_money'));
        $this->assertEquals(100,$seller->fresh()->balance);
        $this->assertDatabaseCount('seller_withdraw_requests',0);
        // Cash/USDT still require an enabled recipient profile; seller bank details are optional.
        foreach([1,3] as $method) {
            try { app(WithdrawalService::class)->apply(Request::create('/withdraw','POST',['w_type'=>$method,'amount'=>'1']),$seller,true); $this->fail(); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        $withdrawal = app(WithdrawalService::class)->apply(Request::create('/withdraw','POST',['w_type'=>2,'amount'=>'1']),$seller,true);
        $this->assertSame('', $withdrawal->iban_details['account_number']);
        $this->assertEquals(0, $withdrawal->status);
    }

    public function testSellerCannotSubmitTwoPendingRequests()
    {
        $seller=$this->user('seller');
        DB::table('currencies')->insert([['code'=>'USD','exchange_rate'=>1],['code'=>'TRY','exchange_rate'=>50.5]]);
        app(WithdrawalService::class)->apply($this->ibanRequest(),$seller);
        try { app(WithdrawalService::class)->apply($this->ibanRequest(),$seller); $this->fail(); }
        catch(ValidationException $e) { $this->assertArrayHasKey('amount',$e->errors()); }
        $this->assertEquals(79.50,$seller->fresh()->balance);
    }

    public function testIbanRejectionRestoresBalanceOnlyOnce()
    {
        $user=$this->user(); $admin=$this->user('admin','+905321234568');
        $withdrawal=app(WithdrawalService::class)->apply($this->ibanRequest(),$user,true);
        $request=Request::create('/review','POST',['remarks'=>'Rejected']); $request->setUserResolver(function()use($admin){return $admin;});
        app(WithdrawalService::class)->reviewIban($request,$withdrawal->id,false);
        $this->assertEquals(100,$user->fresh()->balance);
        try { app(WithdrawalService::class)->reviewIban($request,$withdrawal->id,false); $this->fail(); }
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409,$e->getStatusCode()); }
        $this->assertEquals(100,$user->fresh()->balance);
    }

    public function testIbanApprovalRecordsPaymentOnlyOnce()
    {
        $user=$this->user(); $admin=$this->user('admin','+905321234568');
        $withdrawal=app(WithdrawalService::class)->apply($this->ibanRequest(),$user,true);
        $request=Request::create('/review','POST',['amount'=>9999]); $request->setUserResolver(function()use($admin){return $admin;});
        app(WithdrawalService::class)->reviewIban($request,$withdrawal->id,true);
        $this->assertDatabaseHas('payments',['amount'=>20.50,'payment_method'=>'iban_payment']);
        try { app(WithdrawalService::class)->reviewIban($request,$withdrawal->id,true); $this->fail(); }
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409,$e->getStatusCode()); }
        $this->assertDatabaseCount('payments',1);
    }

    public function testPhoneCountryPolicyAndSharedCallingCodes()
    {
        $this->assertSame('+905321234567',app(PhoneNumber::class)->normalize('+90 532 123 4567','TR'));
        foreach(['+447911123456','+12425551234'] as $phone) {
            try { app(PhoneNumber::class)->normalize($phone); $this->fail(); }
            catch(ValidationException $e) { $this->assertArrayHasKey('phone',$e->errors()); }
        }
        $this->getJson('/api/v2/auth/phone/countries')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.country','TR');
    }

    public function testSmsSubmissionStoresHashAndEnforcesCooldown()
    {
        $this->postJson('/api/v2/auth/phone/send-code',['phone'=>'+905321234567','purpose'=>'register'])->assertOk();
        $row=DB::table('phone_verifications')->first();
        $this->assertTrue(Hash::check($this->code,$row->code_hash));
        $this->assertNotSame($this->code,$row->code_hash);
        $this->postJson('/api/v2/auth/phone/send-code',['phone'=>'+905321234567','purpose'=>'register'])->assertStatus(429);
        Http::assertSentCount(1);
    }

    public function testSmsRecipientFailureDoesNotReportSuccess()
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*'=>Http::response(['code'=>'00000','result'=>[['phone'=>'905321234567','status'=>'F0015']]])]);
        $this->postJson('/api/v2/auth/phone/send-code',['phone'=>'+905321234567','purpose'=>'register'])->assertStatus(503);
        $this->assertNull(DB::table('phone_verifications')->value('code_hash'));
    }

    public function testMissingProviderCredentialsNeverSends()
    {
        config(['chuanx.appsecret'=>null]);
        $this->postJson('/api/v2/auth/phone/send-code',['phone'=>'+905321234567','purpose'=>'register'])->assertStatus(503);
        Http::assertNothingSent();
    }

    public function testPhoneRegistrationAndSingleUseCode()
    {
        $this->seedCode('+905321234567','register');
        $payload=['phone'=>'+905321234567','code'=>'123456','name'=>'New Customer','password'=>'password123','password_confirmation'=>'password123'];
        $this->postJson('/api/v2/auth/phone/register',$payload)->assertOk()->assertJsonPath('user.type','customer')->assertJsonStructure(['access_token']);
        $this->assertDatabaseHas('users',['phone_normalized'=>'+905321234567','reputation_score'=>100]);
        $this->assertNotNull(User::first()->phone_verified_at);
        $this->assertNull(User::first()->email_verified_at);
        $this->postJson('/api/v2/auth/phone/register',$payload)->assertStatus(422);
        $this->assertDatabaseCount('users',1);
    }

    public function testGenericCodeRegistersARegularCustomerWithoutAnSmsRecord()
    {
        $payload=['phone'=>'+905321234567','code'=>'89001','name'=>'Generic Customer',
            'password'=>'password123','password_confirmation'=>'password123'];
        $this->postJson('/api/v2/auth/phone/register',$payload)
            ->assertOk()->assertJsonPath('user.type','customer');
        $user = User::firstOrFail();
        $this->assertSame('customer', $user->user_type);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertSame(0, DB::table('phone_verifications')->count());
        $this->assertNull($user->email_verified_at);
    }

    public function testGenericCodeWorksForExistingSellerLoginAndPasswordReset()
    {
        $user = $this->user('seller');
        $this->postJson('/api/v2/auth/phone/login', ['phone'=>$user->phone,'code'=>'89001'])
            ->assertOk()->assertJsonPath('user.type','seller');
        $this->postJson('/api/v2/auth/phone/reset-password', [
            'phone'=>$user->phone,'code'=>'89001','password'=>'newpassword123',
            'password_confirmation'=>'newpassword123',
        ])->assertOk();
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
        $this->assertSame('seller', $user->fresh()->user_type);
    }

    public function testExpiredWrongPurposeAndWrongPhoneCodesAreRejected()
    {
        $user=$this->user();
        foreach([['register',false],['login',true]] as $case) {
            $this->seedCode($user->phone,$case[0],0,$case[1]);
            $this->postJson('/api/v2/auth/phone/login',['phone'=>$user->phone,'code'=>'123456'])->assertStatus(422);
        }
        $this->seedCode('+905321234568','login');
        $this->postJson('/api/v2/auth/phone/login',['phone'=>$user->phone,'code'=>'123456'])->assertStatus(422);
    }

    public function testFiveWrongAttemptsDisableCorrectCode()
    {
        $user=$this->user(); $this->seedCode($user->phone,'login');
        for($i=0;$i<5;$i++) { $this->postJson('/api/v2/auth/phone/login',['phone'=>$user->phone,'code'=>'000000'])->assertStatus(422); }
        $this->assertSame(5,(int)DB::table('phone_verifications')->value('attempts'));
        $this->postJson('/api/v2/auth/phone/login',['phone'=>$user->phone,'code'=>'123456'])->assertStatus(422);
    }

    public function testPhoneLoginBySmsAndThenPassword()
    {
        $user=$this->user('seller'); $this->seedCode($user->phone,'login');
        $this->postJson('/api/v2/auth/phone/login',['phone'=>$user->phone,'code'=>'123456'])->assertOk()->assertJsonPath('user.type','seller');
        $this->postJson('/api/v2/auth/phone/login',['phone'=>$user->phone,'password'=>'password123'])->assertOk();
        $this->postJson('/api/v2/auth/phone/login',['phone'=>$user->phone,'code'=>'123456'])->assertStatus(422);
    }

    public function sellerLoginEndpoints()
    {
        return [['/login'], ['/users/phone'], ['/api/v2/auth/phone/login'], ['/api/v2/auth/login']];
    }

    /** @dataProvider sellerLoginEndpoints */
    public function testSellerSuccessfulLoginRecordsTimeWithoutChangingRegistrationOrUpdatedTime($url)
    {
        $user = $this->user('seller'); $user->phone_verified_at = now(); $user->save();
        $created = $user->getRawOriginal('created_at'); $updated = $user->getRawOriginal('updated_at');
        $this->travelTo(\Carbon\Carbon::parse('2026-11-22 16:20:30'));
        $response = $this->post($url, ['mode'=>'login', 'phone'=>$user->phone, 'password'=>'password123']);
        if (strpos($url, '/api/') === 0) $response->assertOk(); else $response->assertRedirect();
        $this->assertSame('2026-11-22 16:20:30', $user->fresh()->last_login_at);
        $this->assertSame($created, $user->fresh()->getRawOriginal('created_at'));
        $this->assertSame($updated, $user->fresh()->getRawOriginal('updated_at'));
        $this->travelBack();
    }

    public function testFailedLoginAndPasswordResetDoNotChangeLastLoginTime()
    {
        $user = $this->user('seller'); $user->phone_verified_at = now();
        $user->last_login_at = '2026-11-01 12:00:00'; $user->save();
        $this->postJson('/api/v2/auth/phone/login', ['phone'=>$user->phone, 'password'=>'incorrect'])
            ->assertStatus(422);
        $this->assertSame('2026-11-01 12:00:00', $user->fresh()->last_login_at);
        $this->postJson('/api/v2/auth/phone/reset-password', ['phone'=>$user->phone, 'code'=>'89001',
            'password'=>'newpassword123', 'password_confirmation'=>'newpassword123'])->assertOk();
        $this->assertSame('2026-11-01 12:00:00', $user->fresh()->last_login_at);
    }

    public function testVerificationCodeLoginRecordsSellerTime()
    {
        $user = $this->user('seller');
        $this->travelTo(\Carbon\Carbon::parse('2026-11-22 16:30:00'));
        $this->postJson('/api/v2/auth/phone/login', ['phone'=>$user->phone, 'code'=>'89001'])->assertOk();
        $this->assertSame('2026-11-22 16:30:00', $user->fresh()->last_login_at);
        $this->travel(60)->seconds();
        $this->postJson('/api/v2/auth/phone/login', ['phone'=>$user->phone, 'password'=>'password123'])->assertOk();
        $this->assertSame('2026-11-22 16:31:00', $user->fresh()->last_login_at);
        $this->travelBack();
    }

    public function testPrivilegedAndBannedAccountsCannotUsePhoneLogin()
    {
        $user=$this->user('admin'); $this->seedCode($user->phone,'login');
        $this->postJson('/api/v2/auth/phone/login',['phone'=>$user->phone,'code'=>'123456'])->assertStatus(422);
        $user->user_type='customer'; $user->banned=1; $user->save();
        $this->postJson('/api/v2/auth/phone/login',['phone'=>$user->phone,'code'=>'123456'])->assertStatus(422);
    }

    public function testPhoneResetRevokesTokensAndCannotReuseCode()
    {
        $user=$this->user(); $user->createToken('old'); $this->seedCode($user->phone,'reset_password');
        $payload=['phone'=>$user->phone,'code'=>'123456','password'=>'newpassword123','password_confirmation'=>'newpassword123'];
        $this->postJson('/api/v2/auth/phone/reset-password',$payload)->assertOk();
        $this->assertTrue(Hash::check('newpassword123',$user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens',0);
        $this->postJson('/api/v2/auth/phone/reset-password',$payload)->assertStatus(422);
    }

    public function testDuplicateLegacyPhoneAccountsRequireResolution()
    {
        $this->user(); $this->user('customer','905321234567');
        $this->postJson('/api/v2/auth/phone/send-code',['phone'=>'+905321234567','purpose'=>'login'])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function testLegacySmsCodeCannotResetPhonePassword()
    {
        $user=$this->user(); $user->verification_code='654321'; $user->save();
        $this->postJson('/api/v2/auth/password/confirm_reset',['verification_code'=>'654321','password'=>'newpassword'])->assertOk()->assertJsonPath('result',false);
        $this->assertTrue(Hash::check('password123',$user->fresh()->password));
    }

    public function testLegacyPhoneSignupRequiresOtp()
    {
        $this->postJson('/api/v2/auth/signup',['register_by'=>'phone','email_or_phone'=>'+905321234567','name'=>'Customer','password'=>'password123','password_confirmation'=>'password123'])->assertStatus(422);
        $this->assertDatabaseCount('users',0);
    }

    public function testBuyerIbanReviewModalsDoNotRequireAShop()
    {
        $user = $this->user();
        $withdrawal = app(WithdrawalService::class)->apply($this->ibanRequest(), $user, true);
        $controller = app(\App\Http\Controllers\SellerWithdrawRequestController::class);
        $request = Request::create('/modal', 'POST', ['id'=>$user->id, 'seller_withdraw_request_id'=>$withdrawal->id]);
        $admin = $this->user('admin', '+905321234568');
        $request->setUserResolver(function () use ($admin) { return $admin; });
        $this->assertSame('backend.sellers.seller_withdraw_requests.iban_payment_modal', $controller->payment_modal($request)->name());
        $this->assertSame('backend.sellers.seller_withdraw_requests.iban_refuse_modal', $controller->refuse_modal($request)->name());
    }

    public function testCartPhoneLoginUsesVerificationAndBannedRules()
    {
        $user = $this->user();
        $payload = ['country_code'=>'90','phone'=>'5321234567','password'=>'password123'];
        $this->postJson('/users/login/cart', $payload)->assertStatus(422);
        $user->phone_verified_at = now(); $user->save();
        $this->post('/users/login/cart', $payload)->assertRedirect();
        $this->assertAuthenticatedAs($user);
        auth()->logout();
        $user->banned = 1; $user->save();
        $this->postJson('/users/login/cart', $payload)->assertStatus(422);
        $this->assertGuest();
    }

    public function testLegacyEmailVerificationRequiresCodeAndStillAcceptsValidCode()
    {
        $user = $this->user(); $user->email = 'customer@example.test'; $user->save();
        $this->postJson('/api/v2/auth/confirm_code', ['user_id'=>$user->id])->assertStatus(422);
        $this->assertNull($user->fresh()->email_verified_at);
        $user->verification_code = '654321'; $user->save();
        $this->postJson('/api/v2/auth/confirm_code', ['user_id'=>$user->id,'verification_code'=>'654321','verify_by'=>'email'])->assertOk()->assertJsonPath('result',true);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
