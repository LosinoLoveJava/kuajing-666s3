<?php

namespace Tests\Feature;

use App\Http\Controllers\SellerController;
use App\Models\Shop;
use App\Models\User;
use App\Services\SellerApproval;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SellerApprovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:',
            'cache.default'=>'array', 'session.driver'=>'array']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('user_type'); $t->integer('pid')->default(0); $t->boolean('banned')->default(0);
        });
        Schema::create('shops', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->integer('verification_status')->default(0);
            $t->text('verification_info')->nullable(); $t->integer('view_base_num')->default(0); $t->integer('view_inc_num')->default(0); $t->timestamps();
        });
        Schema::create('business_settings', function (Blueprint $t) { $t->increments('id'); $t->string('type'); $t->string('value'); });
        DB::table('business_settings')->insert(['type'=>'salesman_reviews_switch', 'value'=>'1']);
        DB::table('users')->insert([
            ['id'=>1,'user_type'=>'seller','pid'=>10], ['id'=>2,'user_type'=>'seller','pid'=>20],
            ['id'=>10,'user_type'=>'salesman','pid'=>0], ['id'=>20,'user_type'=>'salesman','pid'=>0],
            ['id'=>30,'user_type'=>'admin','pid'=>0], ['id'=>40,'user_type'=>'staff','pid'=>0],
            ['id'=>50,'user_type'=>'customer','pid'=>0],
        ]);
        DB::table('shops')->insert(['id'=>1,'user_id'=>1,'verification_status'=>0,'verification_info'=>'[]']);
        require_once database_path('migrations/2026_11_18_130000_add_seller_approval_audit.php');
        (new \AddSellerApprovalAudit)->up();
    }

    private function update($operator = 30, array $data = [], $action = 'toggle')
    {
        $request = Request::create('/admin/sellers/approved', 'POST', array_merge(['id'=>1,'status'=>1,'version'=>0], $data));
        $request->headers->set('Accept', 'application/json');
        return app(SellerApproval::class)->update($request, $operator ? User::find($operator) : null, $action);
    }

    public function testAdminApprovalIsCommittedWithAuditAndCacheInvalidation()
    {
        Cache::put('verified_sellers_id', []);
        $this->assertSame(['id'=>1,'status'=>1,'version'=>1], $this->update());
        $this->assertNull(Cache::get('verified_sellers_id'));
        $this->assertDatabaseHas('shops', ['id'=>1,'verification_status'=>1,'approval_version'=>1]);
        $this->assertDatabaseHas('seller_approval_logs', ['shop_id'=>1,'user_id'=>1,'operator_id'=>30,
            'operator_role'=>'admin','old_status'=>0,'new_status'=>1,'old_version'=>0,'new_version'=>1,'action'=>'toggle']);
        $this->assertNotEmpty(DB::table('seller_approval_logs')->value('request_id'));
    }

    public function testSalesmanCanReviewOnlyTheirSellerWhileReviewsAreEnabled()
    {
        $this->assertSame(1, $this->update(10)['status']);
        $this->assertDatabaseHas('seller_approval_logs', ['operator_id'=>10,'operator_role'=>'salesman']);
    }

    public function deniedOperators()
    {
        return [[null, true], [1, true], [50, true], [20, true], [10, false]];
    }

    /** @dataProvider deniedOperators */
    public function testUnauthorizedRequestsCannotChangeStateOrCreateAudit($operator, $enabled)
    {
        DB::table('business_settings')->update(['value'=>$enabled ? '1' : '0']);
        try { $this->update($operator); $this->fail('Request should be denied.'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $this->assertDatabaseHas('shops', ['id'=>1,'verification_status'=>0,'approval_version'=>0]);
        $this->assertDatabaseCount('seller_approval_logs', 0);
    }

    public function testStaffCanReviewAndBannedOperatorsCannot()
    {
        $this->assertSame(1, $this->update(40)['status']);
        DB::table('users')->where('id',30)->update(['banned'=>1]);
        $this->expectException(HttpException::class);
        $this->update(30, ['version'=>1,'status'=>0]);
    }

    public function testRuntimeReviewSettingIsNotTakenFromCachedSettings()
    {
        Cache::put('business_settings', collect([(object)['type'=>'salesman_reviews_switch','value'=>'1']]));
        DB::table('business_settings')->update(['value'=>'0']);
        $this->expectException(HttpException::class);
        $this->update(10);
    }

    public function testOldRequestsCannotOverwriteNewerStatusEvenAfterAFullOnOffCycle()
    {
        $this->update();
        $this->update(30, ['status'=>0,'version'=>1]);
        try { $this->update(30); $this->fail('Stale version should conflict.'); }
        catch (HttpResponseException $e) {
            $this->assertSame(409, $e->getResponse()->getStatusCode());
            $this->assertSame(['id'=>1,'status'=>0,'version'=>2], $e->getResponse()->getData(true)['approval']);
        }
        $this->assertDatabaseCount('seller_approval_logs', 2);
        $this->assertDatabaseHas('shops', ['verification_status'=>0,'approval_version'=>2]);
    }

    public function invalidPayloads()
    {
        return [[['status'=>2]], [['status'=>'yes']], [['version'=>null]], [['version'=>-1]], [['version'=>4294967295]], [['id'=>[1]]]];
    }

    /** @dataProvider invalidPayloads */
    public function testInvalidInputDoesNotWrite($data)
    {
        try { $this->update(30, $data); $this->fail('Invalid input should fail.'); }
        catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        $this->assertDatabaseCount('seller_approval_logs', 0);
        $this->assertDatabaseHas('shops', ['verification_status'=>0,'approval_version'=>0]);
    }

    public function testAuditInsertFailureRollsBackTheApprovalChange()
    {
        DB::unprepared("CREATE TRIGGER fail_audit BEFORE INSERT ON seller_approval_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        try { $this->update(); $this->fail('Audit failure should abort approval.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('audit unavailable', $e->getMessage()); }
        $this->assertDatabaseHas('shops', ['verification_status'=>0,'approval_version'=>0]);
    }

    public function testRejectClearsDocumentsAndLogsTheSameVersionedWorkflow()
    {
        $this->update();
        $this->assertSame(0, $this->update(30, ['status'=>0,'version'=>1], 'reject')['status']);
        $this->assertDatabaseHas('shops', ['verification_status'=>0,'verification_info'=>null,'approval_version'=>2]);
        $this->assertDatabaseHas('seller_approval_logs', ['action'=>'reject','old_status'=>1,'new_status'=>0]);
    }

    public function testRepeatedMigrationPreservesExistingApprovalsAndAudit()
    {
        $this->update();
        (new \AddSellerApprovalAudit)->up();
        $this->assertDatabaseHas('shops', ['verification_status'=>1,'approval_version'=>1]);
        $this->assertDatabaseCount('seller_approval_logs', 1);
    }

    public function testApprovalStatusReadUsesTheSameOwnershipBoundary()
    {
        $this->actingAs(User::find(10));
        $response = app(SellerController::class)->approvalStatus(1);
        $this->assertSame(['id'=>1,'status'=>0,'version'=>0], $response->getData(true)['approval']);
        $this->actingAs(User::find(20));
        $this->expectException(HttpException::class);
        app(SellerController::class)->approvalStatus(1);
    }

    public function testEveryApprovalWriteRouteRequiresPostAndCsrf()
    {
        foreach (['sellers.approved','salesman.sellers.approved','sellers.approve','sellers.reject'] as $name) {
            $route = app('router')->getRoutes()->getByName($name);
            $this->assertSame(['POST'], $route->methods());
            $this->assertContains(\App\Http\Middleware\VerifyCsrfToken::class, $route->gatherMiddleware());
        }
    }
}
