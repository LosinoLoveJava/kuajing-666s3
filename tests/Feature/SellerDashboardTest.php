<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Middleware\RetiredMembershipFeatures;
use App\Services\WithdrawalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SellerDashboardTest extends TestCase
{
    private function websiteSession($seller = null)
    {
        $this->withoutMiddleware([
            \App\Http\Middleware\Language::class,
            \App\Http\Middleware\HttpsProtocol::class,
            \App\Http\Middleware\CheckForMaintenanceMode::class,
        ]);
        if ($seller) {
            // Exercise real session authentication, without actingAs or a bearer token.
            request()->attributes->set('portal', 'seller');
            $cookie = config('session.portal_cookie', config('session.cookie'));
            config(['session.portal_cookie' => $cookie, 'session.cookie' => $cookie.'_seller']);
            session()->setName(config('session.cookie'));
            $guard = auth()->guard('web');
            session()->start();
            session()->put($guard->getName(), $seller->id);
            session()->save();
            $this->withUnencryptedCookies([config('session.cookie') => session()->getId()]);
            auth()->forgetGuards();
        }
        return $this;
    }

    public function testWebsiteCardsReadCurrentSellerWithExistingSession()
    {
        $seller = $this->account();
        $seller->reputation_score = 87; $seller->save();
        $this->websiteSession($seller)->getJson('/seller/dashboard/reputation?user_id=999')
            ->assertOk()->assertJsonPath('data.score', 87);
        $this->getJson('/seller/dashboard/reputation/history')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/seller/dashboard/rewards/progress')->assertOk()
            ->assertJsonPath('data.completed_orders', 0)->assertJsonCount(2, 'data.milestones');
    }

    public function testWebsiteCardsRequireLogin()
    {
        $this->websiteSession();
        foreach (['reputation', 'reputation/history', 'rewards/progress'] as $path) {
            $this->getJson('/seller/dashboard/'.$path)->assertUnauthorized();
        }
    }

    public function testWebsiteRewardClaimRequiresCsrfAndCreditsOnlyOnce()
    {
        $seller = $this->account(); $this->orders($seller, 7);
        $this->websiteSession($seller);
        $token = session()->token();
        $this->app->instance('env', 'production');
        $url = '/seller/dashboard/rewards/orders_7/claim';
        $this->postJson($url)->assertStatus(419);
        $this->assertDatabaseCount('seller_reward_claims', 0);
        $this->withHeader('X-CSRF-TOKEN', $token)->postJson($url)->assertOk()
            ->assertJsonPath('data.amount', '100.00');
        $this->postJson($url)->assertOk()->assertJsonPath('data.already_claimed', true);
        $this->assertDatabaseCount('seller_reward_claims', 1);
        $this->assertSame('112.34', $seller->fresh()->getRawOriginal('balance'));
    }

    public function testWebsiteCardTranslationsRenderAndPreserveCustomTranslations()
    {
        Schema::create('translations', function (Blueprint $t) {
            $t->increments('id'); $t->string('lang'); $t->string('lang_key'); $t->text('lang_value'); $t->timestamps();
        });
        DB::table('translations')->insert(['lang'=>'cn', 'lang_key'=>'healthy', 'lang_value'=>'自定义状态']);
        require_once database_path('migrations/2026_10_22_120000_add_seller_dashboard_translations.php');
        $migration = new \AddSellerDashboardTranslations;
        $migration->up(); $migration->up();
        $this->assertDatabaseCount('translations', 288);
        app()->setLocale('cn');
        $html = view('seller.partials.dashboard_account_cards')->render();
        $this->assertStringContainsString('信誉分', $html);
        $this->assertStringContainsString('奖励进度', $html);
        $this->assertSame('自定义状态', translate('Healthy'));
        app()->setLocale('sa');
        $this->assertStringContainsString('تقدم المكافآت', view('seller.partials.dashboard_account_cards')->render());
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:',
            'cache.default'=>'array', 'session.driver'=>'array']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('user_type');
            $t->boolean('banned')->default(false); $t->integer('reputation_score')->default(100);
            $t->decimal('balance',20,2)->default(0); $t->timestamps();
        });
        Schema::create('shops', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->decimal('bzj_money',20,2)->default(50); $t->timestamps();
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->morphs('tokenable'); $t->string('name'); $t->string('token',64)->unique();
            $t->text('abilities')->nullable(); $t->timestamp('last_used_at')->nullable(); $t->timestamps();
        });
        Schema::create('reputation_logs', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->integer('operator_id');
            $t->integer('old_score'); $t->integer('new_score'); $t->string('reason'); $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->integer('seller_id');
            $t->string('payment_status')->default('paid'); $t->string('delivery_status')->default('delivered');
        });
        Schema::create('order_details', function (Blueprint $t) { $t->increments('id'); $t->integer('order_id'); });
        Schema::create('refund_requests', function (Blueprint $t) {
            $t->increments('id'); $t->integer('order_detail_id'); $t->integer('refund_status');
        });
        Schema::create('wallets', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->decimal('amount',20,2);
            $t->string('payment_method')->nullable(); $t->text('payment_details')->nullable();
            $t->integer('approval')->default(0); $t->integer('offline_payment')->default(0);
            $t->integer('type')->default(1); $t->integer('operator_id')->default(0); $t->timestamps();
        });
        Schema::create('business_settings', function (Blueprint $t) {
            $t->increments('id'); $t->string('type'); $t->text('value')->nullable();
        });
        Schema::create('currencies', function (Blueprint $t) { $t->increments('id'); $t->string('code'); });
        DB::table('currencies')->insert(['id'=>1,'code'=>'USD']);
        DB::table('business_settings')->insert(['type'=>'system_default_currency','value'=>'1']);
        Schema::create('seller_withdraw_requests', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->decimal('amount',20,2);
            $t->text('message'); $t->integer('status'); $t->integer('viewed');
            $t->integer('type'); $t->integer('w_type'); $t->integer('t_type');
            $t->text('remarks'); $t->text('iban_details')->nullable(); $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->increments('id'); $t->integer('seller_id'); $t->decimal('amount',20,2);
            $t->string('payment_method'); $t->text('payment_details')->nullable(); $t->integer('t_type'); $t->timestamps();
        });
        require_once database_path('migrations/2026_10_22_100000_create_seller_reward_claims.php');
        (new \CreateSellerRewardClaims)->up();
    }

    private function account($role = 'seller', $shop = true)
    {
        $user = new User;
        $user->forceFill(['name'=>'Fixture', 'user_type'=>$role, 'balance'=>'12.34']); $user->save();
        if ($shop && $role === 'seller') { DB::table('shops')->insert(['user_id'=>$user->id]); }
        return $user;
    }

    private function orders($seller, $count)
    {
        for ($i=0; $i<$count; $i++) {
            $id = DB::table('orders')->insertGetId(['user_id'=>999, 'seller_id'=>$seller->id]);
            // Multiple details must never multiply the number of orders.
            DB::table('order_details')->insert([['order_id'=>$id], ['order_id'=>$id]]);
        }
    }

    private function getPaths()
    {
        return ['/api/v2/seller/reputation', '/api/v2/seller/reputation/history', '/api/v2/seller/rewards/progress'];
    }

    public function testAllSellerEndpointsRequireAuthentication()
    {
        foreach ($this->getPaths() as $path) { $this->getJson($path)->assertUnauthorized(); }
        $this->postJson('/api/v2/seller/rewards/orders_7/claim')->assertUnauthorized();
    }

    public function testOtherRolesBannedAndShoplessSellersAreRejected()
    {
        $banned = $this->account(); $banned->banned = true; $banned->save();
        $accounts = [$this->account('customer'), $this->account('admin'), $this->account('staff'),
            $this->account('seller',false), $banned];
        foreach ($accounts as $user) {
            foreach ($this->getPaths() as $path) { $this->actingAs($user,'sanctum')->getJson($path)->assertForbidden(); }
            $this->actingAs($user,'sanctum')->postJson('/api/v2/seller/rewards/orders_7/claim')->assertForbidden();
        }
        $this->assertDatabaseCount('wallets',0);
    }

    public function testRealSanctumBearerTokenWorksWithoutWebSession()
    {
        $seller=$this->account(); $token=$seller->createToken('dashboard')->plainTextToken;
        foreach ($this->getPaths() as $path) {
            $this->withHeaders(['Authorization'=>'Bearer '.$token])->getJson($path)->assertOk();
        }
        $this->assertDatabaseCount('wallets',0);
    }

    public function testScoreOnlyReturnsSelfAndUsesExistingScoreSystem()
    {
        $seller=$this->account(); $other=$this->account();
        $seller->reputation_score=68; $seller->save();
        $this->actingAs($seller,'sanctum')->getJson('/api/v2/seller/reputation?user_id='.$other->id)
            ->assertOk()->assertExactJson(['success'=>true,'data'=>['score'=>68,'max_score'=>100]])
            ->assertHeader('Cache-Control','no-store, private');
        $this->getJson('/api/v2/user/reputation')->assertOk()->assertJsonPath('data.score',68);
    }

    public function testReputationHistoryFiltersPaginatesAndHidesOperator()
    {
        $seller=$this->account(); $other=$this->account();
        foreach ([$seller,$other,$seller] as $user) {
            DB::table('reputation_logs')->insert(['user_id'=>$user->id,'operator_id'=>999,
                'old_score'=>100,'new_score'=>90,'reason'=>'Adjustment','created_at'=>now(),'updated_at'=>now()]);
        }
        $response=$this->actingAs($seller,'sanctum')->getJson('/api/v2/seller/reputation/history?per_page=1&user_id='.$other->id)
            ->assertOk()->assertJsonPath('meta.total',2)->assertJsonPath('meta.last_page',2)
            ->assertJsonPath('data.0.id',3)->assertJsonPath('data.0.change',-10)
            ->assertHeader('Cache-Control','no-store, private');
        $this->assertArrayNotHasKey('operator_id',$response->json('data.0'));
        $this->assertArrayNotHasKey('user_id',$response->json('data.0'));
        $this->getJson('/api/v2/seller/reputation/history?per_page=1&page=2')->assertOk()->assertJsonPath('data.0.id',1);
        $this->getJson('/api/v2/seller/reputation/history?page=20')->assertOk()->assertJsonCount(0,'data');
        foreach (['page=0','page=abc','per_page=0','per_page=51'] as $query) {
            $this->getJson('/api/v2/seller/reputation/history?'.$query)->assertStatus(422);
        }
    }

    public function testNewSellerHasEmptyHistoryAndZeroProgressWithoutAnyCredit()
    {
        $seller=$this->account();
        $this->actingAs($seller,'sanctum')->getJson('/api/v2/seller/reputation/history')->assertOk()->assertJsonCount(0,'data');
        $this->getJson('/api/v2/seller/rewards/progress')->assertOk()
            ->assertJsonPath('data.completed_orders',0)->assertJsonPath('data.period','lifetime')
            ->assertJsonPath('data.milestones.0.target_orders',7)->assertJsonPath('data.milestones.0.amount','100.00')
            ->assertJsonPath('data.milestones.1.target_orders',14)->assertJsonPath('data.milestones.1.amount','300.00')
            ->assertJsonPath('data.milestones.0.status','in_progress')->assertJsonPath('data.milestones.0.remaining_orders',7)
            ->assertHeader('Cache-Control','no-store, private');
        $this->assertDatabaseCount('wallets',0);
        $this->assertEquals(12.34,$seller->fresh()->balance);
    }

    public function testOrderProgressExcludesUnpaidUndeliveredFullyRefundedAndOtherSellers()
    {
        $seller=$this->account(); $other=$this->account(); $this->orders($seller,10); $this->orders($other,20);
        DB::table('orders')->where('id',1)->update(['payment_status'=>'unpaid']);
        DB::table('orders')->where('id',2)->update(['delivery_status'=>'pending']);
        foreach (DB::table('order_details')->whereIn('order_id',[3,4])->get() as $detail) {
            if ($detail->order_id==4 && $detail->id%2==0) { continue; } // partial refund still counts
            DB::table('refund_requests')->insert(['order_detail_id'=>$detail->id,'refund_status'=>1]);
        }
        $this->actingAs($seller,'sanctum')->getJson('/api/v2/seller/rewards/progress?user_id='.$other->id)
            ->assertOk()->assertJsonPath('data.completed_orders',7)->assertJsonPath('data.milestones.0.can_claim',true)
            ->assertJsonPath('data.milestones.0.progress_percent',100)->assertJsonPath('data.milestones.1.progress_percent',50);
        $this->getJson('/api/v2/user/completed-orders/count')->assertOk()->assertJsonPath('data.count',7);
    }

    public function testClaimAtSevenCreditsOnlyServerAmountAndIsIdempotent()
    {
        $seller=$this->account(); $other=$this->account(); $this->orders($seller,7);
        $first=$this->actingAs($seller,'sanctum')->postJson('/api/v2/seller/rewards/orders_7/claim',
            ['user_id'=>$other->id,'amount'=>'999999','currency'=>'AED','completed_orders'=>999])
            ->assertOk()->assertJsonPath('data.amount','100.00')->assertJsonPath('data.currency','USD')
            ->assertJsonPath('data.already_claimed',false)->assertHeader('Cache-Control','no-store, private');
        $this->postJson('/api/v2/seller/rewards/orders_7/claim')->assertOk()
            ->assertJsonPath('data.already_claimed',true)->assertJsonPath('data.claim_id',$first->json('data.claim_id'));
        $this->assertEquals(112.34,$seller->fresh()->balance);
        $this->assertEquals(12.34,$other->fresh()->balance);
        $this->assertDatabaseCount('seller_reward_claims',1); $this->assertDatabaseCount('wallets',1);
        $this->assertDatabaseHas('wallets',['user_id'=>$seller->id,'payment_method'=>'seller_order_reward',
            'amount'=>100,'approval'=>1,'offline_payment'=>0,'type'=>1]);
        $this->getJson('/api/v2/seller/rewards/progress')->assertOk()->assertJsonPath('data.milestones.0.status','claimed')
            ->assertJsonPath('data.milestones.0.can_claim',false)->assertJsonPath('data.total_claimed_amount','100.00');
        $this->assertEquals(50,DB::table('shops')->where('user_id',$seller->id)->value('bzj_money'));
    }

    public function testBothMilestonesCanBeClaimedIndependentlyOnceForFourHundredTotal()
    {
        $seller=$this->account(); $this->orders($seller,14);
        $this->actingAs($seller,'sanctum')->postJson('/api/v2/seller/rewards/orders_14/claim')->assertOk()->assertJsonPath('data.amount','300.00');
        $this->postJson('/api/v2/seller/rewards/orders_7/claim')->assertOk();
        $this->postJson('/api/v2/seller/rewards/orders_14/claim')->assertOk()->assertJsonPath('data.already_claimed',true);
        $this->assertEquals(412.34,$seller->fresh()->balance);
        $this->assertDatabaseCount('seller_reward_claims',2); $this->assertDatabaseCount('wallets',2);
        $this->getJson('/api/v2/seller/rewards/progress')->assertOk()->assertJsonPath('data.total_claimed_amount','400.00');
    }

    public function testUnknownAndUnmetMilestonesNeverCredit()
    {
        $seller=$this->account(); $this->orders($seller,6);
        $this->actingAs($seller,'sanctum')->getJson('/api/v2/seller/rewards/progress')->assertOk()
            ->assertJsonPath('data.milestones.0.progress_percent',85.71)
            ->assertJsonPath('data.milestones.0.remaining_orders',1)->assertJsonPath('data.milestones.0.can_claim',false);
        $this->actingAs($seller,'sanctum')->postJson('/api/v2/seller/rewards/orders_7/claim')->assertStatus(422);
        $this->postJson('/api/v2/seller/rewards/orders_14/claim')->assertStatus(422);
        $this->postJson('/api/v2/seller/rewards/invalid/claim')->assertNotFound();
        $this->assertDatabaseCount('wallets',0); $this->assertDatabaseCount('seller_reward_claims',0);
        $this->assertEquals(12.34,$seller->fresh()->balance);
    }

    public function testNonUsdSystemCurrencyDoesNotCreditUsdAsOtherMoney()
    {
        $seller=$this->account(); $this->orders($seller,7);
        DB::table('currencies')->where('id',1)->update(['code'=>'AED']);
        $this->actingAs($seller,'sanctum')->postJson('/api/v2/seller/rewards/orders_7/claim')->assertStatus(503);
        $this->assertDatabaseCount('wallets',0); $this->assertDatabaseCount('seller_reward_claims',0);
        $this->assertEquals(12.34,$seller->fresh()->balance);
    }

    public function testInvalidRewardConfigurationCannotCreateInvalidCredits()
    {
        $seller=$this->account(); $this->orders($seller,7); $this->actingAs($seller,'sanctum');
        foreach ([-100,'0.00','100.001','abc'] as $amount) {
            config(['seller_rewards.milestones.orders_7'=>['orders'=>7,'amount'=>$amount]]);
            $this->getJson('/api/v2/seller/rewards/progress')->assertStatus(503);
            $this->postJson('/api/v2/seller/rewards/orders_7/claim')->assertStatus(503);
        }
        $this->assertDatabaseCount('wallets',0); $this->assertDatabaseCount('seller_reward_claims',0);
        $this->assertEquals(12.34,$seller->fresh()->balance);
    }

    public function testClaimSnapshotAndNoReclaimAfterLaterRefundOrConfigurationChange()
    {
        $seller=$this->account(); $this->orders($seller,7);
        $this->actingAs($seller,'sanctum')->postJson('/api/v2/seller/rewards/orders_7/claim')->assertOk();
        DB::table('orders')->update(['delivery_status'=>'cancelled']);
        config(['seller_rewards.milestones.orders_7'=>['orders'=>8,'amount'=>'900.00']]);
        $this->postJson('/api/v2/seller/rewards/orders_7/claim')->assertOk()->assertJsonPath('data.amount','100.00');
        $this->getJson('/api/v2/seller/rewards/progress')->assertOk()->assertJsonPath('data.completed_orders',0)
            ->assertJsonPath('data.milestones.0.target_orders',7)->assertJsonPath('data.milestones.0.amount','100.00')
            ->assertJsonPath('data.milestones.0.status','claimed');
        $this->assertEquals(112.34,$seller->fresh()->balance);
    }

    public function testLedgerFailureRollsBackWalletAndBalance()
    {
        $seller=$this->account(); $this->orders($seller,7);
        // Simulate a final audit-insert failure AFTER wallet and balance saves.
        DB::statement("CREATE TRIGGER reject_claim BEFORE INSERT ON seller_reward_claims BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");
        try { app(\App\Services\SellerRewards::class)->claim($seller,'orders_7'); $this->fail(); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('audit unavailable',$e->getMessage()); }
        $this->assertEquals(12.34,$seller->fresh()->balance);
        $this->assertDatabaseCount('wallets',0); $this->assertDatabaseCount('seller_reward_claims',0);
    }

    public function testRewardWalletCannotBeReviewedAsRecharge()
    {
        $seller=$this->account(); $this->orders($seller,7);
        $receipt=app(\App\Services\SellerRewards::class)->claim($seller,'orders_7');
        try {
            app(\App\Http\Controllers\WalletController::class)->updateApproved(Request::create('/review','POST',[
                'id'=>$receipt['wallet_id'],'status'=>1])); $this->fail();
        } catch (HttpException $e) { $this->assertEquals(409,$e->getStatusCode()); }
        $this->assertEquals(112.34,$seller->fresh()->balance);
    }

    public function testRollbackCannotErasePaidClaimsAndEnableDoublePayout()
    {
        $seller=$this->account(); $this->orders($seller,7);
        app(\App\Services\SellerRewards::class)->claim($seller,'orders_7');
        try { (new \CreateSellerRewardClaims)->down(); $this->fail(); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('must be preserved',$e->getMessage()); }
        $this->assertDatabaseCount('seller_reward_claims',1);
        $receipt=app(\App\Services\SellerRewards::class)->claim($seller,'orders_7');
        $this->assertTrue($receipt['already_claimed']);
        $this->assertEquals(112.34,$seller->fresh()->balance);
    }

    public function testDepositRechargeAndModalEntryPointsRejectTypeTwoWithoutWrites()
    {
        $seller=$this->account();
        $calls = [
            [\App\Http\Controllers\WalletController::class,'offline_recharge'],
            [\App\Http\Controllers\Api\V2\WalletController::class,'offline_recharge'],
            [\App\Http\Controllers\ManualPaymentMethodController::class,'offline_recharge_modal'],
            [\App\Http\Controllers\ManualPaymentMethodController::class,'admin_recharge_modal'],
        ];
        foreach ($calls as [$class,$method]) {
            try { app($class)->$method(Request::create('/recharge','POST',['type'=>2,'amount'=>10])); $this->fail(); }
            catch (ValidationException $e) { $this->assertArrayHasKey('type',$e->errors()); }
        }
        $this->assertDatabaseCount('wallets',0);
        $this->assertEquals(50,DB::table('shops')->value('bzj_money'));
        $this->assertEquals(12.34,$seller->fresh()->balance);
    }

    public function testLegacyGuaranteeRoutesAreRetiredEvenWhenCalledDirectly()
    {
        foreach (['website.guarantee','sellers.guarantee_money_modal','sellers.setbzj'] as $name) {
            $request=Request::create('/retired');
            $route=(new Route('GET','retired',function () {}))->name($name);
            $request->setRouteResolver(function () use ($route) { return $route; });
            try { app(RetiredMembershipFeatures::class)->handle($request,function () { $this->fail(); }); $this->fail(); }
            catch (HttpException $e) { $this->assertEquals(404,$e->getStatusCode()); }
        }
        try { app(\App\Http\Controllers\SellerController::class)->setbzj(Request::create('/set','POST',['bzj'=>999])); $this->fail(); }
        catch (HttpException $e) { $this->assertEquals(404,$e->getStatusCode()); }
    }

    public function testHistoricalDepositIbanRefundRemainsReviewableOnlyOnce()
    {
        $seller=$this->account(); $admin=$this->account('admin');
        $id=DB::table('seller_withdraw_requests')->insertGetId(['user_id'=>$seller->id,'amount'=>20.50,
            'type'=>2,'w_type'=>4,'t_type'=>1,'status'=>0,'viewed'=>0,'message'=>'Historical','remarks'=>'']);
        $request=Request::create('/review','POST',['remarks'=>'Refund']);
        $request->setUserResolver(function () use ($admin) { return $admin; });
        app(WithdrawalService::class)->reviewIban($request,$id,false);
        try { app(WithdrawalService::class)->reviewIban($request,$id,false); $this->fail(); }
        catch (HttpException $e) { $this->assertEquals(409,$e->getStatusCode()); }
        $this->assertEquals(70.50,DB::table('shops')->value('bzj_money'));
        $this->assertEquals(12.34,$seller->fresh()->balance);
    }

    public function testHistoricalDepositIbanApprovalRecordsPaymentOnceWithoutMovingBalances()
    {
        $seller=$this->account(); $admin=$this->account('admin');
        $id=DB::table('seller_withdraw_requests')->insertGetId(['user_id'=>$seller->id,'amount'=>20.50,
            'type'=>2,'w_type'=>4,'t_type'=>1,'status'=>0,'viewed'=>0,'message'=>'Historical','remarks'=>'']);
        $request=Request::create('/review','POST',['amount'=>99999]);
        $request->setUserResolver(function () use ($admin) { return $admin; });
        app(WithdrawalService::class)->reviewIban($request,$id,true);
        try { app(WithdrawalService::class)->reviewIban($request,$id,true); $this->fail(); }
        catch (HttpException $e) { $this->assertEquals(409,$e->getStatusCode()); }
        $this->assertDatabaseHas('payments',['amount'=>20.50,'payment_method'=>'iban_payment']);
        $this->assertDatabaseCount('payments',1);
        $this->assertEquals(50,DB::table('shops')->value('bzj_money'));
        $this->assertEquals(12.34,$seller->fresh()->balance);
    }

    public function testSellerTemplatesRemoveDepositEntryButKeepWalletIbanAndMessagePolling()
    {
        $nav=file_get_contents(resource_path('views/seller/inc/seller_nav.blade.php'));
        $side=file_get_contents(resource_path('views/seller/inc/seller_sidenav.blade.php'));
        $wallet=file_get_contents(resource_path('views/seller/money_withdraw_requests/index.blade.php'));
        $admin=file_get_contents(resource_path('views/backend/sellers/index.blade.php'));
        $this->assertStringNotContainsString('bzj_money',$nav);
        $this->assertStringNotContainsString('mandatory_payment_switch',$side);
        $this->assertStringNotContainsString('compulsory_margin_amount',$side);
        $this->assertStringContainsString('seller-notification-config',$side);
        $this->assertStringContainsString("route('conversations.check_new_msg'",$side);
        $this->assertStringContainsString('seller-notifications.js',file_get_contents(resource_path('views/seller/layouts/app.blade.php')));
        $this->assertStringNotContainsString('show_make_wallet_recharge_modal(2)',$wallet);
        $this->assertStringNotContainsString('value="2">{{translate(\'Guarantee',$wallet);
        $this->assertStringContainsString("@include('partials.iban_form')",$wallet);
        $this->assertStringContainsString('iban_number',file_get_contents(resource_path('views/partials/iban_form.blade.php')));
        $this->assertStringContainsString('show_make_wallet_recharge_modal(1)',$wallet);
        $this->assertStringNotContainsString('Guarantee Money',$admin);
    }
}
