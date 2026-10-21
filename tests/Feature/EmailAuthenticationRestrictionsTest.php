<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmailAuthenticationRestrictionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array', 'portals.domain' => 'example.test']);
        DB::purge('sqlite');
        Http::fake();
        Notification::fake();
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('email')->nullable();
            $t->string('phone')->nullable(); $t->string('phone_normalized')->nullable();
            $t->timestamp('phone_verified_at')->nullable(); $t->timestamp('email_verified_at')->nullable();
            $t->string('password'); $t->string('user_type')->default('customer');
            $t->boolean('banned')->default(false); $t->integer('reputation_score')->default(100);
            $t->string('remember_token')->nullable(); $t->timestamps();
        });
        Schema::create('countries', function (Blueprint $t) {
            $t->increments('id'); $t->string('code'); $t->string('name'); $t->integer('status');
        });
        DB::table('countries')->insert(['code' => 'AE', 'name' => 'United Arab Emirates', 'status' => 1]);
        Schema::create('translations', function (Blueprint $t) {
            $t->increments('id'); $t->string('lang'); $t->string('lang_key'); $t->text('lang_value'); $t->timestamps();
        });
        Schema::create('carts', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('temp_user_id')->nullable();
        });
        Schema::create('uploads', function (Blueprint $t) { $t->increments('id'); });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->morphs('tokenable'); $t->string('name'); $t->string('token', 64)->unique();
            $t->text('abilities')->nullable(); $t->timestamp('last_used_at')->nullable(); $t->timestamps();
        });
    }

    private function account($role)
    {
        $user = new User;
        $user->name = 'Fixture'; $user->email = $role.'@example.test';
        $user->phone = '+971501234567'; $user->phone_normalized = $user->phone;
        $user->phone_verified_at = now(); $user->email_verified_at = now();
        $user->password = Hash::make('password123'); $user->user_type = $role;
        $user->save();
        return $user;
    }

    public static function publicPortals()
    {
        return [['www'], ['seller']];
    }

    /** @dataProvider publicPortals */
    public function testLegacyPublicLoginEntriesRedirectToPhone($portal)
    {
        foreach (['/login', '/users/login'] as $path) {
            $this->get('http://'.$portal.'.example.test'.$path)->assertRedirect('http://'.$portal.'.example.test/users/phone');
        }
        foreach (['/register', '/users/registration'] as $path) {
            $this->get('http://'.$portal.'.example.test'.$path)
                ->assertRedirect('http://'.$portal.'.example.test/users/phone?mode=register');
        }
    }

    public function testManagementAndOtherNonPublicPortalsKeepEmailForm()
    {
        foreach (['admin', 'sales', 'assig'] as $portal) {
            $request = \Illuminate\Http\Request::create('http://'.$portal.'.example.test/login');
            app()->instance('request', $request);
            app('url')->setRequest($request);
            $this->assertSame('auth.login', app(\App\Http\Controllers\Auth\LoginController::class)->showLoginForm()->name());
        }
    }

    public static function publicRoles()
    {
        return [['customer'], ['seller']];
    }

    /** @dataProvider publicRoles */
    public function testPublicEmailCredentialsCannotUseManagementOrCartLogin($role)
    {
        $user = $this->account($role);
        foreach (['www', 'seller', 'admin'] as $portal) {
            $this->from('http://'.$portal.'.example.test/login')
                ->post('http://'.$portal.'.example.test/login', [
                    'email' => $user->email, 'password' => 'password123', 'user_type' => 'admin',
                ])->assertRedirect();
            $this->assertGuest();
        }
        $this->postJson('/users/login/cart', ['email' => $user->email, 'password' => 'password123'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertGuest();
        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertDatabaseCount('users', 1);
    }

    public static function managementRoles()
    {
        return [['admin'], ['staff'], ['salesman'], ['delivery_boy']];
    }

    /** @dataProvider managementRoles */
    public function testExistingManagementEmailAuthenticationStillWorks($role)
    {
        $user = $this->account($role);
        $this->post('http://admin.example.test/login', ['email' => $user->email, 'password' => 'password123'])
            ->assertRedirect(route(in_array($role, ['admin', 'staff'], true) ? 'admin.dashboard' : 'dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function testWrongAdminPasswordDoesNotLogIn()
    {
        $user = $this->account('admin');
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertRedirect();
        $this->assertGuest();
    }

    public function testEmailSignupIsRejectedBeforeCreatingAnyAccountOrNotification()
    {
        $payload = ['name' => 'Fixture', 'email' => 'new@example.test',
            'password' => 'password123', 'password_confirmation' => 'password123'];
        $this->postJson('/register', $payload)->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/register', $payload + ['phone' => '+971501234567', 'code' => '89001'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
        $this->postJson('/api/v2/auth/signup', ['register_by' => 'email', 'email_or_phone' => 'new@example.test'] + $payload)
            ->assertStatus(422)->assertJsonValidationErrors('register_by');
        $this->postJson('/api/v2/auth/signup', ['email_or_phone' => 'new@example.test', 'password' => 'password123'])
            ->assertStatus(422);
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
        Http::assertNothingSent(); Notification::assertNothingSent();
    }

    /** @dataProvider publicRoles */
    public function testLegacyApiEmailLoginCannotBeBypassedWithRoleParameter($role)
    {
        $user = $this->account($role);
        foreach (['customer', 'seller', 'admin', 'delivery_boy'] as $spoofedRole) {
            $this->postJson('/api/v2/auth/login', [
                'email' => $user->email, 'password' => 'password123', 'user_type' => $spoofedRole,
            ])->assertStatus($spoofedRole === 'delivery_boy' ? 401 : 422);
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest();
    }

    /** @dataProvider publicRoles */
    public function testPhonePasswordLoginStillWorksOnLegacyWebAndApiEndpoints($role)
    {
        $user = $this->account($role);
        $payload = ['phone' => $user->phone, 'password' => 'password123'];
        $this->post('/login', $payload)->assertRedirect(route($role === 'seller' ? 'seller.dashboard' : 'dashboard'));
        $this->assertAuthenticatedAs($user);
        auth()->logout();
        $this->postJson('/api/v2/auth/login', $payload)->assertOk()->assertJsonPath('user.type', $role);
        $this->postJson('/api/v2/auth/login', ['email' => $user->phone, 'password' => 'password123'])
            ->assertOk()->assertJsonPath('user.type', $role);
        $this->post('/users/login/cart', $payload)->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function testPublicPhonePageAndSharedPromptsContainNoEmailAuthentication()
    {
        $finder = app('view')->getFinder();
        $paths = $finder->getPaths();
        $finder->prependLocation(base_path('tests/Fixtures/views'));
        $finder->flush();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        app('request')->setLaravelSession(app('session.store'));
        try {
            foreach (['login', 'register', 'reset_password'] as $mode) {
                $request = \Illuminate\Http\Request::create('/users/phone?mode='.$mode);
                $html = app(\App\Http\Controllers\PhoneAccessController::class)->show($request)->render();
                $this->assertStringNotContainsString('Email login', $html);
                $this->assertStringNotContainsString('name="email"', $html);
            }
            foreach (['frontend.user_login', 'frontend.user_registration', 'frontend.partials.phone_login_prompt'] as $view) {
                $html = view($view)->render();
                $this->assertStringContainsString('/users/phone', $html);
                $this->assertStringNotContainsString('name="email"', $html);
            }
        } finally {
            $finder->setPaths($paths); $finder->flush();
        }
        foreach (['view_cart', 'product_details', 'digital_product_details'] as $template) {
            $source = file_get_contents(resource_path('views/frontend/'.$template.'.blade.php'));
            $this->assertStringContainsString("@include('frontend.partials.phone_login_prompt')", $source);
            $this->assertStringNotContainsString("route('cart.login.submit')", $source);
            $this->assertStringNotContainsString('toggleEmailPhone', $source);
        }
    }
}
