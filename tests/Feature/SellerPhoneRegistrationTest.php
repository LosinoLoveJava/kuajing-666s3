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
            $t->boolean('is_virtual')->default(false); $t->decimal('balance',20,2)->default(0);
            $t->boolean('banned')->default(false); $t->integer('reputation_score')->default(100);
            $t->string('remember_token')->nullable(); $t->integer('pid')->default(0); $t->integer('referred_by')->nullable();
            foreach (['tpwd','usdt_address','node','deep','identity_card_front','identity_card_back','certtype'] as $column) { $t->string($column)->default(''); }
            $t->timestamps();
        });
        Schema::create('shops', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id')->unique(); $t->string('name'); $t->string('address');
            $t->string('phone'); $t->string('slug'); $t->integer('seller_package_id'); $t->integer('product_upload_limit');
            $t->integer('views')->default(0); $t->date('package_invalid_at'); $t->integer('verification_status')->default(0);
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
        Schema::create('sellers', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->unique(); $t->timestamps(); });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->increments('id'); $t->string('tokenable_type'); $t->integer('tokenable_id'); $t->string('name');
            $t->string('token')->unique(); $t->text('abilities')->nullable(); $t->timestamp('last_used_at')->nullable(); $t->timestamps();
        });
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

    /** @dataProvider optionalDocumentCases */
    public function testBackDocumentIsOptionalAndOnlySubmittedUploadsAreClaimed($documents, $front, $back, $claimed)
    {
        $payload = $this->payload();
        unset($payload['identity_card_front'], $payload['identity_card_back']);
        $this->post('/shops', array_merge($payload, $documents))->assertRedirect(route('shops.index'));
        $user = User::firstOrFail();
        $this->assertSame('seller', $user->user_type);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertSame($front, $user->identity_card_front);
        $this->assertSame($back, $user->identity_card_back);
        $this->assertDatabaseHas('shops', ['user_id' => $user->id, 'verification_status' => 0]);
        $this->assertDatabaseHas('seller_package_payments', ['user_id' => $user->id]);
        foreach ([1, 2, 3] as $id) {
            $this->assertDatabaseHas('uploads', ['id' => $id, 'user_id' => in_array($id, $claimed, true) ? $user->id : 0]);
        }
        $this->assertDatabaseHas('uploads', ['id' => 4, 'user_id' => 999]);
        $this->assertNull(DB::table('phone_verifications')->value('code_hash'));
        Notification::assertNothingSent(); Http::assertNothingSent();
    }

    public function optionalDocumentCases(): array
    {
        return [
            'front only' => [['identity_card_front' => 1], '1', '', [1]],
            'front with empty back' => [['identity_card_front' => 1, 'identity_card_back' => ''], '1', '', [1]],
            'front with null back' => [['identity_card_front' => 1, 'identity_card_back' => null], '1', '', [1]],
            'both images' => [['identity_card_front' => 1, 'identity_card_back' => 2], '1', '2', [1, 2]],
        ];
    }

    public function testMissingFrontDocumentCannotCreateSellerOrConsumeCode()
    {
        $payload = $this->payload();
        unset($payload['identity_card_front']);
        $this->postJson('/shops', $payload)->assertStatus(422)->assertJsonValidationErrors('identity_card_front');
        foreach (['', null] as $front) {
            $this->postJson('/shops', $this->payload(['identity_card_front' => $front, 'identity_card_back2' => 3]))
                ->assertStatus(422)->assertJsonValidationErrors('identity_card_front');
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('shops', 0);
        $this->assertDatabaseCount('seller_package_payments', 0);
        $this->assertDatabaseHas('uploads', ['id' => 3, 'user_id' => 0]);
        $this->assertTrue(Hash::check('123456', DB::table('phone_verifications')->value('code_hash')));
    }

    public function testAuthenticatedApplicationAlsoRequiresFrontDocument()
    {
        $admin = new User;
        $admin->name = 'Admin'; $admin->password = Hash::make('password123');
        $admin->user_type = 'admin'; $admin->save();
        $this->actingAs($admin)->postJson('/shops', $this->payload(['identity_card_front' => '']))
            ->assertStatus(422)->assertJsonValidationErrors('identity_card_front');
        $this->assertDatabaseCount('shops', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function testRemovedLicenseFieldIsIgnoredAndItsUploadIsNotClaimed()
    {
        $this->post('/shops', $this->payload(['identity_card_back2' => 3]))->assertRedirect(route('shops.index'));
        $this->assertDatabaseHas('uploads', ['id' => 3, 'user_id' => 0]);
        $this->assertDatabaseHas('uploads', ['id' => 1, 'user_id' => User::firstOrFail()->id]);
    }

    public function testSellerWithOnlyFrontDocumentCanUseNormalPhoneLogin()
    {
        $this->post('/shops', $this->payload(['identity_card_back' => '', 'code' => '89001']))
            ->assertRedirect(route('shops.index'));
        $user = User::firstOrFail();
        auth()->logout();
        $this->post('/users/phone', ['mode' => 'login', 'phone' => $user->phone, 'password' => 'password123'])
            ->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('shops', 1);
    }

    public function testShopApplicationRequiresFrontKeepsBackOptionalAndRemovesLicense()
    {
        $finder = app('view')->getFinder();
        $finder->prependLocation(base_path('tests/Fixtures/views'));
        $finder->flush();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        app('request')->setLaravelSession(app('session.store'));
        \Illuminate\Support\Facades\Cache::put('business_settings', collect(), 60);
        $view = app(\App\Http\Controllers\ShopController::class)->create(Request::create('/shops/create'));
        $html = $view->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new \DOMXPath($dom);
        foreach (['identity_card_front', 'identity_card_back'] as $field) {
            $inputs = $xpath->query('//input[@name="' . $field . '"]');
            $this->assertSame(1, $inputs->length);
            $this->assertSame($field === 'identity_card_front', $inputs->item(0)->hasAttribute('required'));
            $labels = $xpath->query('//input[@name="' . $field . '"]/ancestor::div[contains(@class,"form-group")][1]/label');
            $this->assertSame(1, $labels->length);
            $this->assertSame($field === 'identity_card_front', strpos($labels->item(0)->textContent, '*') !== false);
        }
        $this->assertSame(0, $xpath->query('//input[@name="identity_card_back2"]')->length);
        $this->assertStringNotContainsString('营业执照', $html);
        $this->assertStringContainsString('identity-front-error', $html);
        $this->assertStringContainsString("form.addEventListener('submit'", $html);
        $this->assertSame(1, $xpath->query('//input[@name="name" and @required]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="address" and @required]')->length);
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
        foreach ([['identity_card_front'=>'not-an-id'], ['identity_card_front'=>4], ['identity_card_back'=>5],
            ['identity_card_front'=>999], ['identity_card_back'=>'1,2'],
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
            $this->assertStringContainsString('type="hidden" name="country" value="AE"', $html);
            $this->assertStringContainsString('name="national_phone" value=""', $html);
            $this->assertStringContainsString('type="hidden" name="phone"', $html);
            $this->assertStringContainsString('assets/js/intlTelutils.js', $html);
            $this->assertStringNotContainsString('International phone number', $html);
        }
    }

    private function operator($role = 'admin')
    {
        $user = new User;
        $user->name = 'Management'; $user->password = Hash::make('password123');
        $user->user_type = $role; $user->email = $role.'@example.test'; $user->save();
        return $user;
    }

    /** @dataProvider managedSellerEntrypoints */
    public function testManagedSellerCreationUsesPhoneAndCanReallyLogIn($role, $route, $virtual)
    {
        $operator = $this->operator($role);
        $payload = $this->payload(); unset($payload['code']);
        $this->actingAs($operator)->post(route($route), $payload)
            ->assertRedirect(route($role === 'salesman' ? 'salesman.sellers_index' : 'sellers.index'));
        $seller = User::where('user_type', 'seller')->firstOrFail();
        $this->assertSame('Test Merchant', $seller->name);
        $this->assertSame('+971501234567', $seller->phone);
        $this->assertSame($seller->phone, $seller->phone_normalized);
        $this->assertNotNull($seller->phone_verified_at);
        $this->assertNull($seller->email); $this->assertNull($seller->email_verified_at);
        $this->assertSame($virtual, (int) $seller->is_virtual);
        $this->assertSame($role === 'salesman' ? $operator->id : 0, (int) $seller->pid);
        $this->assertDatabaseHas('shops', ['user_id' => $seller->id, 'name' => 'Test Shop', 'phone' => $seller->phone]);
        $this->assertDatabaseCount('seller_package_payments', 1);
        $this->assertDatabaseHas('uploads', ['id' => 1, 'user_id' => $seller->id]);
        if ($route === 'sellers.store') { $this->assertDatabaseHas('sellers', ['user_id' => $seller->id]); }
        Notification::assertNothingSent(); Http::assertNothingSent();
        auth()->logout();
        $this->post('/users/phone', ['mode' => 'login', 'phone' => $seller->phone, 'password' => 'password123'])
            ->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticatedAs($seller);
    }

    public function managedSellerEntrypoints(): array
    {
        return [['admin', 'shops.store', 1], ['salesman', 'shops.store', 1], ['staff', 'shops.store', 1],
            ['admin', 'sellers.store', 0], ['salesman', 'sellers.store', 0]];
    }

    /** @dataProvider invalidManagedSellerData */
    public function testManagementCannotCreateInvalidOrEmailOnlySeller($changes, $field)
    {
        $this->actingAs($this->operator())->postJson('/shops', $this->payload($changes))
            ->assertStatus(422)->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('users', 1); $this->assertDatabaseCount('shops', 0);
        $this->assertDatabaseCount('seller_package_payments', 0);
        $this->assertDatabaseHas('uploads', ['id' => 1, 'user_id' => 0]);
    }

    public function invalidManagedSellerData(): array
    {
        return [
            [['phone' => '', 'email' => 'new@example.test'], 'phone'],
            [['email' => 'new@example.test'], 'email'], [['phone' => 'not a phone'], 'phone'],
            [['phone' => '+447911123456', 'country' => 'GB'], 'phone'],
            [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
            [['password_confirmation' => 'different'], 'password'],
            [['account_name' => ''], 'account_name'], [['identity_card_front' => ''], 'identity_card_front'],
            [['identity_card_front' => 4], 'identity_card_front'], [['identity_card_front' => 5], 'identity_card_front'],
        ];
    }

    public function testManagementChecksLegacyPhoneDuplicatesAcrossRoles()
    {
        $operator = $this->operator();
        $existing = $this->operator('customer'); $existing->phone = '+971 (50) 123-4567'; $existing->save();
        foreach (['shops.store', 'sellers.store'] as $route) {
            $this->actingAs($operator)->postJson(route($route), $this->payload())
                ->assertStatus(422)->assertJsonValidationErrors('phone');
        }
        $this->assertDatabaseCount('shops', 0); $this->assertDatabaseCount('users', 2);
    }

    public function testManagementMissingPackageDoesNotLeavePartialAccounts()
    {
        DB::table('seller_packages')->delete();
        $this->actingAs($this->operator())->postJson('/shops', $this->payload())->assertStatus(422);
        $this->assertDatabaseCount('users', 1); $this->assertDatabaseCount('shops', 0);
        $this->assertDatabaseHas('uploads', ['id' => 1, 'user_id' => 0]);
    }

    public function testShopFailureRollsBackManagementAccountAndMedia()
    {
        $operator = $this->operator();
        Shop::creating(function () { throw new \RuntimeException('Simulated shop failure'); });
        try {
            app(\App\Services\ManagedPhoneAccounts::class)->createSeller(Request::create('/', 'POST', $this->payload()), $operator);
            $this->fail('Expected the simulated persistence failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated shop failure', $e->getMessage());
        } finally { Shop::flushEventListeners(); }
        $this->assertDatabaseCount('users', 1); $this->assertDatabaseCount('shops', 0);
        $this->assertDatabaseCount('seller_package_payments', 0);
        $this->assertDatabaseHas('uploads', ['id' => 1, 'user_id' => 0]);
    }

    public function testManagementOwnedImagesAreAcceptedWithoutTransferringOwnership()
    {
        $operator = $this->operator();
        DB::table('uploads')->where('id', 1)->update(['user_id' => $operator->id]);
        $seller = app(\App\Services\ManagedPhoneAccounts::class)->createSeller(Request::create('/', 'POST', $this->payload(['identity_card_back' => ''])), $operator);
        $this->assertSame('1', $seller->fresh()->identity_card_front);
        $this->assertSame('', $seller->fresh()->identity_card_back);
        $this->assertDatabaseHas('uploads', ['id' => 1, 'user_id' => $operator->id]);
        $this->assertDatabaseHas('uploads', ['id' => 2, 'user_id' => 0]);
    }

    public function testCustomerCannotUseAuthenticatedShopCreationAsSmsBypass()
    {
        $this->actingAs($this->operator('customer'))->postJson('/shops', $this->payload())->assertStatus(403);
        $this->assertDatabaseCount('users', 1); $this->assertDatabaseCount('shops', 0);
    }

    public function testSellerEditingMigratesOldAccountToPhoneWithoutDeletingHistoricalEmail()
    {
        $operator = $this->operator();
        $seller = app(\App\Services\ManagedPhoneAccounts::class)->createSeller(Request::create('/', 'POST', $this->payload()), $operator);
        $seller->phone = null; $seller->phone_normalized = null; $seller->phone_verified_at = null;
        $seller->email = 'legacy@example.test'; $seller->save();
        $seller->createToken('old-device');
        $shop = $seller->shop;
        $this->actingAs($operator)->patch(route('sellers.update', $shop->id), [
            'name' => 'Updated Merchant', 'phone' => '+971-50-123-4567', 'country' => 'AE', 'views' => 4,
        ])->assertRedirect(route('sellers.index'));
        $seller->refresh();
        $this->assertSame('+971501234567', $seller->phone_normalized);
        $this->assertSame('legacy@example.test', $seller->email);
        $this->assertNotNull($seller->phone_verified_at);
        $this->assertDatabaseHas('shops', ['id' => $shop->id, 'phone' => $seller->phone, 'views' => 4]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        auth()->logout();
        $this->post('/users/phone', ['mode' => 'login', 'phone' => $seller->phone, 'password' => 'password123'])->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticatedAs($seller);
    }

    public function testSalesmanCanOnlyEditOwnSellersAndCannotStealAnotherNumber()
    {
        $operator = $this->operator('salesman');
        $seller = app(\App\Services\ManagedPhoneAccounts::class)->createSeller(Request::create('/', 'POST', $this->payload()), $operator);
        $shop = $seller->shop;
        $this->actingAs($operator)->patch(route('salesman.sellers.update', $shop->id), [
            'name' => 'Updated', 'phone' => $seller->phone,
        ])->assertRedirect(route('salesman.sellers_index'));
        $other = $this->operator(); $other->phone = '+971501234568'; $other->phone_normalized = $other->phone; $other->save();
        $this->patchJson(route('salesman.sellers.update', $shop->id), ['name' => 'Bad', 'phone' => $other->phone])
            ->assertStatus(422)->assertJsonValidationErrors('phone');
        $seller->pid = $other->id; $seller->save();
        $this->getJson(route('salesman.sellers.edit', encrypt($shop->id)))->assertStatus(403);
        $this->patchJson(route('salesman.sellers.update', $shop->id), ['name' => 'Bad', 'phone' => $seller->phone])->assertStatus(403);
        $this->assertSame('Updated', $seller->fresh()->name);
    }

    /** @dataProvider managedCustomerEntrypoints */
    public function testManagementCustomerCreationAlsoUsesPhoneAndHashesPasswords($role, $route)
    {
        $operator = $this->operator($role);
        $this->actingAs($operator)->post(route($route), ['name' => 'Customer', 'phone' => '+971 50 123 4567',
            'country' => 'AE', 'password' => 'password123', 'password_confirmation' => 'password123', 'balance' => '12.50'])
            ->assertRedirect(route($role === 'salesman' ? 'salesman.customers.index' : 'customers.index'));
        $user = User::where('user_type', 'customer')->firstOrFail();
        $this->assertNull($user->email); $this->assertNotNull($user->phone_verified_at);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->assertSame('+971501234567', $user->phone_normalized);
        $this->assertEquals(12.50, $user->balance);
        if ($role === 'salesman') { $this->assertSame($operator->id, (int) $user->referred_by); }
        auth()->logout();
        $this->post('/users/phone', ['mode' => 'login', 'phone' => $user->phone, 'password' => 'password123'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        Notification::assertNothingSent(); Http::assertNothingSent();
    }

    public function managedCustomerEntrypoints(): array
    {
        return [['admin', 'customers.store'], ['salesman', 'salesman.customers.store']];
    }

    public function testAllManagementCreationFormsUseSharedPhoneFieldsWithDefaultAeAndNoEmail()
    {
        $finder = app('view')->getFinder();
        $finder->prependLocation(base_path('tests/Fixtures/views')); $finder->flush();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        app('request')->setLaravelSession(app('session.store'));
        \Illuminate\Support\Facades\Cache::put('business_settings', collect(), 60);
        $this->actingAs($this->operator());
        foreach (['backend.sellers.seller_form', 'salesman.sellers.seller_form', 'backend.sellers.create',
            'backend.customer.customers.create', 'salesman.customers.create'] as $view) {
            $html = view($view, ['countries' => app(\App\Services\PhoneNumber::class)->countries()])->render();
            $dom = new \DOMDocument; @$dom->loadHTML($html); $xpath = new \DOMXPath($dom);
            $this->assertSame(0, $xpath->query('//input[@name="email"]')->length, $view);
            $this->assertSame(1, $xpath->query('//input[@name="phone" and @type="hidden"]')->length, $view);
            $this->assertSame(1, $xpath->query('//input[@name="national_phone" and @required]')->length, $view);
            $this->assertSame(1, $xpath->query('//input[@name="country" and @value="AE" and @type="hidden"]')->length, $view);
            $this->assertSame(0, $xpath->query('//select[@name="country"]')->length, $view);
            $this->assertStringContainsString('>+971</span>', $html);
            $this->assertSame(1, $xpath->query('//input[@name="name"]')->length, $view);
            $this->assertStringNotContainsString('grecaptcha', $html);
            $this->assertStringContainsString('if (button) button.addEventListener', $html);
        }
    }

    public function testSellerEditingFormDisplaysExistingPhoneNotEmail()
    {
        $finder = app('view')->getFinder(); $finder->prependLocation(base_path('tests/Fixtures/views')); $finder->flush();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        app('request')->setLaravelSession(app('session.store'));
        $operator = $this->operator(); $this->actingAs($operator);
        $seller = app(\App\Services\ManagedPhoneAccounts::class)->createSeller(Request::create('/', 'POST', $this->payload()), $operator);
        $html = app(\App\Http\Controllers\SellerController::class)->edit(encrypt($seller->shop->id))->render();
        $this->assertStringNotContainsString('name="email"', $html);
        $this->assertStringContainsString('name="phone" value="+971501234567"', $html);
        $this->assertStringContainsString('name="national_phone" value="+971501234567"', $html);
    }

    public function testPhoneOnlyCustomersAreVisibleInManagementAndSearchable()
    {
        $user = $this->operator('customer'); $user->phone = '+971501234567'; $user->phone_normalized = $user->phone;
        $user->phone_verified_at = now(); $user->email = null; $user->save();
        $this->actingAs($this->operator());
        $view = app(\App\Http\Controllers\CustomerController::class)->index(Request::create('/', 'GET', ['search' => '501234567']));
        $this->assertSame([$user->id], $view->getData()['users']->pluck('id')->all());
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
