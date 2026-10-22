<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhonePortalAccessTest extends TestCase
{
    use \Tests\Concerns\InteractsWithPortalSessions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        $this->withoutMiddleware([
            \App\Http\Middleware\Language::class,
            \App\Http\Middleware\HttpsProtocol::class,
            \App\Http\Middleware\CheckForMaintenanceMode::class,
        ]);
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('phone');
            $table->string('phone_normalized')->nullable();
            $table->string('password');
            $table->string('user_type');
            $table->boolean('banned')->default(false);
            $table->integer('reputation_score')->default(100);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('countries', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code');
            $table->integer('status');
        });
        DB::table('countries')->insert(['code' => 'AE', 'status' => 1]);
    }

    private function account($role, $banned = false)
    {
        $user = new User;
        $user->forceFill(['name' => 'Test account', 'phone' => '+971501234567',
            'password' => Hash::make('password123'), 'user_type' => $role, 'banned' => $banned])->save();
        return $user;
    }

    /** @dataProvider allowedRoles */
    public function testBackendPhoneLoginAcceptsExistingBackendRoles($role, $route)
    {
        $user = $this->account($role);
        $response = $this->post('/admin/phone-login', ['phone' => '+971 50 123 4567', 'password' => 'password123'])
            ->assertRedirect(\App\Services\PortalContext::dashboardUrl($user));
        $this->assertPortalAuthenticatedAs($user, $response);
    }

    public function allowedRoles()
    {
        return [['admin', 'admin.dashboard'], ['staff', 'admin.dashboard'], ['salesman', 'dashboard']];
    }

    /** @dataProvider rejectedAccounts */
    public function testBackendRejectsOtherRolesBannedAccountsAndWrongPasswords($role, $banned, $password)
    {
        $this->account($role, $banned);
        $this->post('/admin/phone-login', ['phone' => '+971501234567', 'password' => $password])
            ->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    public function rejectedAccounts()
    {
        return [['customer', false, 'password123'], ['seller', false, 'password123'],
            ['admin', true, 'password123'], ['admin', false, 'wrong-password']];
    }

    public function testEmailCannotBeUsedForBackendLogin()
    {
        $this->post('/admin/phone-login', ['email' => 'admin@example.com', 'password' => 'password123'])
            ->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    public function testBackendLoginIsRateLimited()
    {
        $this->account('admin');
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->post('/admin/phone-login', ['phone' => '+971501234567', 'password' => 'wrong'])
                ->assertSessionHasErrors('phone');
        }
        $this->post('/admin/phone-login', ['phone' => '+971501234567', 'password' => 'password123'])->assertStatus(429);
        $this->assertGuest();
    }

    public function testUserEntriesRedirectToPhoneForms()
    {
        $this->get('/users/login')->assertRedirect(route('phone.access'));
        $this->get('/users/registration')->assertRedirect(route('phone.access', ['mode' => 'register']));
        $this->get('/register')->assertRedirect(route('phone.access', ['mode' => 'register']));
        $this->postJson('/register', ['email' => 'test@example.com'])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function testChatOnlyLoadsForAuthenticatedAccountsAndPassesTheirId()
    {
        $this->assertStringNotContainsString('plugin-code.salesmartly.com', view('frontend.partials.salesmartly')->render());
        $user = $this->account('customer');
        $this->actingAs($user);
        $html = view('frontend.partials.salesmartly')->render();
        $this->assertStringContainsString('project_840494_873536_1791129077.js', $html);
        $this->assertStringContainsString('user_id: "'.$user->id.'"', $html);
        auth()->logout();
        $this->assertStringNotContainsString('plugin-code.salesmartly.com', view('frontend.partials.salesmartly')->render());
    }
}
