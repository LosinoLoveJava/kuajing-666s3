<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PortalContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PortalSessionIsolationTest extends TestCase
{
    private $jar = [];
    private $directory;
    private $accounts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/portal-'.bin2hex(random_bytes(8)));
        mkdir($this->directory, 0700, true);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'file', 'session.files' => $this->directory,
            'session.cookie' => 'portal_test', 'session.domain' => '.example.test', 'session.secure' => false,
            'portals.domain' => 'example.test']);
        DB::purge('sqlite');
        $this->withoutMiddleware([\App\Http\Middleware\Language::class, \App\Http\Middleware\HttpsProtocol::class,
            \App\Http\Middleware\CheckForMaintenanceMode::class]);
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('email'); $t->string('phone');
            $t->string('phone_normalized')->nullable(); $t->timestamp('phone_verified_at')->nullable();
            $t->timestamp('email_verified_at')->nullable(); $t->string('password'); $t->string('user_type');
            $t->boolean('banned')->default(false); $t->integer('reputation_score')->default(100);
            $t->rememberToken(); $t->timestamps();
            $t->string('verification_code')->nullable();
        });
        Schema::create('translations', function (Blueprint $t) {
            $t->increments('id'); $t->string('lang'); $t->string('lang_key'); $t->text('lang_value'); $t->timestamps();
        });
        Schema::create('countries', function (Blueprint $t) { $t->increments('id'); $t->string('code'); $t->integer('status'); });
        DB::table('countries')->insert(['code' => 'AE', 'status' => 1]);
        Schema::create('carts', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); });
        foreach (['customer', 'seller', 'admin', 'salesman'] as $index => $role) {
            $user = new User;
            $user->forceFill(['name' => $role, 'email' => $role.'@example.test', 'phone' => '+97150123456'.$index,
                'password' => Hash::make('fixture-password'), 'user_type' => $role, 'phone_verified_at' => now()])->save();
            $this->accounts[$role] = $user;
        }
        foreach (['/__portal/identity', '/seller/__portal_identity', '/admin/__portal_identity', '/salesman/__portal_identity'] as $path) {
            Route::middleware('web')->get($path, function (Request $request) {
                return response()->json(['id' => Auth::id(), 'portal' => PortalContext::current(),
                    'csrf' => $request->session()->token(), 'remembered' => Auth::viaRemember()]);
            });
        }
        Route::middleware('web')->post('/admin/__portal_impersonate', function (Request $request) {
            abort_unless(optional(Auth::user())->user_type === 'admin', 403);
            $user = User::where('user_type', $request->input('role'))->firstOrFail();
            app(\App\Services\PortalLogin::class)->login($user, $request, true);
            return redirect(PortalContext::dashboardUrl($user));
        });
        Route::middleware(['web', \App\Http\Middleware\VerifyCsrfToken::class])->post('/__portal/csrf', function () {
            return response()->json(['id' => Auth::id()]);
        });
    }

    protected function tearDown(): void
    {
        if ($this->directory) { (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($this->directory); }
        parent::tearDown();
    }

    private function browser($method, $url, array $data = [], $referer = null)
    {
        // A new PHP request has a fresh guard/store, while the browser keeps all
        // cookies. Do not use actingAs(), which would hide cross-request bugs.
        Auth::forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        app('cookie')->flushQueuedCookies();
        $host = parse_url($url, PHP_URL_HOST);
        $response = $this->call($method, $url, $data, $this->jar[$host] ?? [], [],
            ['HTTP_REFERER' => $referer ?: '', 'HTTP_ACCEPT' => 'application/json']);
        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertNull($cookie->getDomain(), 'Authentication cookies must be host-only.');
            $this->jar[$host][$cookie->getName()] = $cookie->getValue();
        }
        return $response;
    }

    private function signIn($base, $role, $remember = true)
    {
        $user = $this->accounts[$role];
        $portal = PortalContext::forUser($user);
        if (in_array($role, ['customer', 'seller'], true)) {
            $path = $role === 'seller' ? '/seller/phone' : '/users/phone';
            $data = ['mode' => 'login', 'phone' => $user->phone];
        } else {
            $path = '/login?portal='.$portal;
            $data = ['email' => $user->email];
        }
        $this->browser('POST', $base.$path, $data + ['password' => 'fixture-password', 'remember' => $remember])
            ->assertRedirect();
    }

    public static function hostModes()
    {
        return [['single-host'], ['subdomains']];
    }

    /** @dataProvider hostModes */
    public function testFourAccountsCoexistAndLoggingOutOnlyClearsTheSelectedPortal($mode)
    {
        $bases = $mode === 'single-host' ? array_fill_keys(['customer', 'seller', 'admin', 'salesman'], 'http://localhost') :
            ['customer' => 'http://www.example.test', 'seller' => 'http://seller.example.test',
                'admin' => 'http://admin.example.test', 'salesman' => 'http://sales.example.test'];
        $identities = ['customer' => '/__portal/identity?portal=customer', 'seller' => '/seller/__portal_identity',
            'admin' => '/admin/__portal_identity', 'salesman' => '/salesman/__portal_identity'];
        foreach ($bases as $role => $base) { $this->signIn($base, $role); }
        $csrf = [];
        foreach ($bases as $role => $base) {
            $response = $this->browser('GET', $base.$identities[$role])->assertOk()->assertJsonPath('id', $this->accounts[$role]->id);
            $csrf[$role] = $response->json('csrf');
        }
        $this->assertCount(4, array_unique($csrf));
        foreach ($bases as $role => $base) {
            $portal = PortalContext::forUser($this->accounts[$role]);
            $this->browser('GET', $base.'/logout?portal='.$portal)->assertRedirect();
            $this->browser('GET', $base.$identities[$role])->assertJsonPath('id', null);
            unset($bases[$role]);
            foreach ($bases as $remaining => $other) {
                $this->browser('GET', $other.$identities[$remaining])->assertJsonPath('id', $this->accounts[$remaining]->id)
                    ->assertJsonPath('csrf', $csrf[$remaining]);
            }
        }
    }

    public function testRememberCookiesRestoreOnlyTheirOwnPortalAfterSessionExpiry()
    {
        foreach (array_keys($this->accounts) as $role) { $this->signIn('http://localhost', $role); }
        foreach (array_keys($this->jar['localhost']) as $name) {
            if (strpos($name, 'portal_test_') === 0) { unset($this->jar['localhost'][$name]); }
        }
        foreach ($this->accounts as $role => $user) {
            $portal = PortalContext::forUser($user);
            $this->browser('GET', 'http://localhost/__portal/identity?portal='.$portal)
                ->assertOk()->assertJsonPath('id', $user->id)->assertJsonPath('remembered', true);
        }
        $this->assertCount(4, array_filter(array_keys($this->jar['localhost']), function ($name) { return strpos($name, 'remember_') === 0; }));
    }

    public function testCopiedSessionIdCannotAuthenticateAnotherPortal()
    {
        $this->signIn('http://localhost', 'seller', false);
        $this->jar['localhost']['portal_test_customer'] = $this->jar['localhost']['portal_test_seller'];
        $this->browser('GET', 'http://localhost/__portal/identity?portal=customer')->assertJsonPath('id', null);
    }

    public function testLegacyUnscopedSessionAndRememberCookiesAreIgnored()
    {
        $this->jar['localhost']['portal_test'] = str_repeat('a', 40);
        $this->jar['localhost']['remember_web_'.sha1(\Illuminate\Auth\SessionGuard::class)] = '1|old-token|old-password';
        $this->browser('GET', 'http://localhost/__portal/identity?portal=customer')->assertOk()->assertJsonPath('id', null);
    }

    public function testAdministratorImpersonationPreservesAdministratorAndBuyerSessions()
    {
        $base = 'http://localhost';
        $this->signIn($base, 'admin');
        $this->signIn($base, 'customer');
        $adminCsrf = $this->browser('GET', $base.'/admin/__portal_identity')->json('csrf');
        $this->browser('POST', $base.'/admin/__portal_impersonate', ['role' => 'seller'])->assertRedirect();
        $this->browser('GET', $base.'/seller/__portal_identity')->assertJsonPath('id', $this->accounts['seller']->id);
        $this->browser('GET', $base.'/admin/__portal_identity')->assertJsonPath('id', $this->accounts['admin']->id)->assertJsonPath('csrf', $adminCsrf);
        $this->browser('GET', $base.'/__portal/identity?portal=customer')->assertJsonPath('id', $this->accounts['customer']->id);
    }

    public function testSharedAjaxUsesSameOriginTabContextAndRejectsForeignReferer()
    {
        $this->signIn('http://localhost', 'customer');
        $this->signIn('http://localhost', 'seller');
        $this->browser('GET', 'http://localhost/__portal/identity', [], 'http://localhost/seller/dashboard')
            ->assertJsonPath('id', $this->accounts['seller']->id);
        $this->browser('GET', 'http://localhost/__portal/identity', [], 'http://attacker.test/seller/dashboard')
            ->assertJsonPath('id', $this->accounts['customer']->id);
        $this->browser('GET', 'http://localhost/__portal/identity', [], 'http://localhost:9999/seller/dashboard')
            ->assertJsonPath('id', $this->accounts['customer']->id);
    }

    public function testInvalidPortalCannotSelectArbitrarySessionNamespace()
    {
        foreach (['unknown', ['admin']] as $value) {
            $request = Request::create('http://localhost/__portal_identity', 'GET', ['portal' => $value]);
            $this->assertSame('customer', PortalContext::resolve($request));
        }
    }

    public function testCsrfTokenIsBoundToTheSelectedPortal()
    {
        $this->signIn('http://localhost', 'customer');
        $this->signIn('http://localhost', 'seller');
        $buyer = $this->browser('GET', 'http://localhost/__portal/identity?portal=customer')->json('csrf');
        $seller = $this->browser('GET', 'http://localhost/seller/__portal_identity')->json('csrf');
        $this->app->instance('env', 'production');
        $this->browser('POST', 'http://localhost/__portal/csrf?portal=seller', ['_token' => $buyer])->assertStatus(419);
        $this->browser('POST', 'http://localhost/__portal/csrf?portal=seller', ['_token' => $seller])->assertOk()
            ->assertJsonPath('id', $this->accounts['seller']->id);
    }

    public function testSalesSharedAdminHandlersDoNotSelectTheAdministratorSession()
    {
        $this->signIn('http://localhost', 'admin');
        $this->signIn('http://localhost', 'salesman');
        $this->browser('GET', 'http://localhost/admin/__portal_identity?portal=sales')->assertJsonPath('id', $this->accounts['salesman']->id);
        $this->browser('GET', 'http://localhost/admin/__portal_identity', [], 'http://localhost/dashboard?portal=sales')
            ->assertJsonPath('id', $this->accounts['salesman']->id);
        $this->browser('GET', 'http://localhost/admin/__portal_identity', [], 'http://localhost/admin/sellers?portal=sales')
            ->assertJsonPath('id', $this->accounts['salesman']->id);
        $this->browser('GET', 'http://localhost/__portal/identity', [], 'http://localhost/admin/sellers?portal=sales')
            ->assertJsonPath('id', $this->accounts['salesman']->id);
        $this->browser('GET', 'http://localhost/admin/__portal_identity?portal=admin')->assertJsonPath('id', $this->accounts['admin']->id);
    }

    public function testDeliveryPortalHasItsOwnSessionAndRememberCookie()
    {
        $user = $this->accounts['admin']->replicate();
        $user->user_type = 'delivery_boy'; $user->email = 'delivery@example.test'; $user->save();
        $this->signIn('http://localhost', 'customer');
        $this->browser('POST', 'http://localhost/login?portal=delivery',
            ['email' => $user->email, 'password' => 'fixture-password', 'remember' => true])->assertRedirect();
        $this->browser('GET', 'http://localhost/__portal/identity?portal=delivery')->assertJsonPath('id', $user->id);
        $this->browser('GET', 'http://localhost/__portal/identity?portal=customer')->assertJsonPath('id', $this->accounts['customer']->id);
        $this->assertArrayHasKey('portal_test_delivery', $this->jar['localhost']);
    }

    public function testLegacyPhoneLoginKeepsTheBuyerSignedInWhenLoggingIntoSeller()
    {
        $this->signIn('http://localhost', 'customer');
        $user = $this->accounts['seller'];
        $this->browser('POST', 'http://localhost/users/phone',
            ['mode' => 'login', 'phone' => $user->phone, 'password' => 'fixture-password'])->assertRedirect();
        $this->browser('GET', 'http://localhost/__portal/identity?portal=customer')
            ->assertJsonPath('id', $this->accounts['customer']->id);
        $this->browser('GET', 'http://localhost/seller/__portal_identity')->assertJsonPath('id', $user->id);
    }

    public function testSalesImpersonationKeepsTheAdministratorAndBuyerSignedIn()
    {
        $this->signIn('http://localhost', 'admin');
        $this->signIn('http://localhost', 'customer');
        $this->browser('GET', 'http://localhost/admin/salesmans/login/'.encrypt($this->accounts['salesman']->id))
            ->assertRedirect();
        $this->browser('GET', 'http://localhost/__portal/identity?portal=sales')
            ->assertJsonPath('id', $this->accounts['salesman']->id);
        $this->browser('GET', 'http://localhost/admin/__portal_identity')->assertJsonPath('id', $this->accounts['admin']->id);
        $this->browser('GET', 'http://localhost/__portal/identity?portal=customer')
            ->assertJsonPath('id', $this->accounts['customer']->id);
    }

    public function testVerificationAndPasswordResetLogIntoOnlyTheDestinationPortal()
    {
        $this->signIn('http://localhost', 'admin');
        $this->signIn('http://localhost', 'customer');
        $user = $this->accounts['seller'];
        $user->verification_code = 'fixture-email-code'; $user->save();
        $this->browser('GET', 'http://localhost/verification-confirmation/fixture-email-code?portal=customer')->assertRedirect();
        $this->browser('GET', 'http://localhost/seller/__portal_identity')->assertJsonPath('id', $user->id);
        $this->browser('POST', 'http://localhost/password/reset/email/submit?portal=customer',
            ['email' => $user->email, 'code' => $user->verification_code,
                'password' => 'changed-fixture-password', 'password_confirmation' => 'changed-fixture-password'])->assertRedirect();
        $this->assertTrue(Hash::check('changed-fixture-password', $user->fresh()->password));
        $this->browser('GET', 'http://localhost/seller/__portal_identity')->assertJsonPath('id', $user->id);
        $this->browser('GET', 'http://localhost/admin/__portal_identity')->assertJsonPath('id', $this->accounts['admin']->id);
        $this->browser('GET', 'http://localhost/__portal/identity?portal=customer')
            ->assertJsonPath('id', $this->accounts['customer']->id);
    }

    public function testManagementPhoneLoginCanUseSalesNamespaceAlongsideAdministrator()
    {
        $this->signIn('http://localhost', 'admin');
        $user = $this->accounts['salesman'];
        // The explicit namespace also overrides a management subdomain default.
        $request = Request::create('http://sales.example.test/admin/example?portal=admin');
        $this->assertSame('admin', PortalContext::resolve($request));
        $this->browser('POST', 'http://localhost/admin/phone-login?portal=sales',
            ['phone' => $user->phone, 'password' => 'fixture-password'])->assertRedirect();
        $this->browser('GET', 'http://localhost/__portal/identity?portal=sales')->assertJsonPath('id', $user->id);
        $this->browser('GET', 'http://localhost/admin/__portal_identity')->assertJsonPath('id', $this->accounts['admin']->id);
    }

    public function testSharedBrowserNavigationPersistsPortalAndAjaxDoesNotRedirect()
    {
        $this->signIn('http://localhost', 'seller');
        $this->get('/__portal/identity', ['Referer' => 'http://localhost/seller/dashboard'])
            ->assertRedirect('http://localhost/__portal/identity?portal=seller');
        $this->browser('GET', 'http://localhost/__portal/identity', [], 'http://localhost/seller/dashboard')
            ->assertOk()->assertJsonPath('id', $this->accounts['seller']->id);
    }
}
