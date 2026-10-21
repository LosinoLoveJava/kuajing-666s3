<?php

namespace Tests\Feature;

use App\Http\Middleware\RetiredMembershipFeatures;
use App\Models\Shop;
use App\Models\User;
use App\Services\AccountMembership;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HighestMembershipTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:', 'cache.default'=>'array','session.driver'=>'array']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('user_type')->default('customer');
            $t->integer('reputation_score')->default(100); $t->integer('pid')->default(0);
            $t->integer('customer_package_id')->nullable(); $t->integer('remaining_uploads')->default(0);
            $t->decimal('balance',20,2)->default(0); $t->timestamps();
        });
        Schema::create('seller_packages', function (Blueprint $t) {
            $t->increments('id'); $t->integer('amount'); $t->integer('product_upload_limit');
            $t->integer('max_profit'); $t->integer('is_default')->default(0); $t->timestamps();
        });
        DB::table('seller_packages')->insert([
            ['id'=>4,'amount'=>0,'product_upload_limit'=>10,'max_profit'=>10,'is_default'=>1],
            ['id'=>5,'amount'=>666,'product_upload_limit'=>1000,'max_profit'=>20,'is_default'=>0],
            ['id'=>6,'amount'=>999,'product_upload_limit'=>2000,'max_profit'=>30,'is_default'=>0],
        ]);
        Schema::create('customer_packages', function (Blueprint $t) {
            $t->increments('id'); $t->integer('amount'); $t->integer('product_upload'); $t->timestamps();
        });
        Schema::create('shops', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->integer('seller_package_id')->nullable();
            $t->integer('product_upload_limit')->default(0); $t->date('package_invalid_at')->nullable();
            $t->integer('verification_status')->default(0); $t->decimal('admin_to_pay',20,2)->default(0); $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->boolean('published')->default(1); $t->timestamps();
        });
        Schema::create('customer_products', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->timestamps();
        });
    }

    private function account($type='customer', $pid=0)
    {
        $user=new User; $user->name='Fixture'; $user->user_type=$type; $user->pid=$pid; $user->save();
        return $user;
    }

    private function seller()
    {
        $user=$this->account('seller');
        $shop=new Shop; $shop->user_id=$user->id; $shop->seller_package_id=4;
        $shop->package_invalid_at='2020-01-01'; $shop->save();
        return $user;
    }

    public function testNewAndEditedShopsAlwaysReceiveHighestPermanentBenefits()
    {
        $user=$this->seller(); $shop=$user->shop;
        $this->assertEquals(6, $shop->seller_package_id);
        $this->assertEquals(2000, $shop->product_upload_limit);
        $this->assertEquals('9999-12-31', $shop->package_invalid_at);
        $shop->seller_package_id=4; $shop->product_upload_limit=1; $shop->package_invalid_at='2020-01-01'; $shop->save();
        $this->assertEquals(6,$shop->fresh()->seller_package_id);
        $this->assertEquals(0,$shop->fresh()->verification_status);
        $this->assertEquals(30,app(AccountMembership::class)->sellerPackage()->max_profit);
    }

    public function testExpiredLegacyShopUsesHighestCapacityIncludingExactBatchBoundary()
    {
        $user=$this->seller();
        DB::table('shops')->update(['seller_package_id'=>4,'product_upload_limit'=>1,'package_invalid_at'=>'2020-01-01']);
        DB::table('products')->insert(['user_id'=>$user->id]);
        $membership=app(AccountMembership::class);
        $this->assertTrue($membership->canUpload($user,1999));
        $this->assertFalse($membership->canUpload($user,2000));
        $this->assertFalse($membership->canUpload($user,-1));
        $this->assertTrue(seller_package_validity_check($user->id));
    }

    public function testNonSellerAndMissingShopCannotObtainSellerPrivileges()
    {
        $this->assertFalse(app(AccountMembership::class)->canUpload($this->account()));
        $this->assertFalse(app(AccountMembership::class)->canUpload($this->account('seller')));
        $this->assertFalse(seller_package_validity_check(9999));
    }

    public function testRePublishingAlreadyPublishedProductDoesNotConsumeAnExtraSlot()
    {
        $user=$this->seller();
        DB::table('seller_packages')->where('id',6)->update(['product_upload_limit'=>1]);
        DB::table('products')->insert(['user_id'=>$user->id]);
        $this->assertTrue(app(AccountMembership::class)->canUpload($user,0,true));
        $this->assertFalse(app(AccountMembership::class)->canUpload($user,1,true));
    }

    public function testCustomerWithNoConfiguredTiersDoesNotNeedToBuyAPackage()
    {
        $user=$this->account();
        $this->assertNull(app(AccountMembership::class)->customerPackage());
        $this->assertTrue(app(AccountMembership::class)->canUploadClassified($user));
        $this->assertFalse(app(AccountMembership::class)->canUploadClassified($this->account('admin')));
    }

    public function testCustomerHighestPackageAppliesOnCreationAndCannotBeDowngraded()
    {
        DB::table('customer_packages')->insert([['id'=>1,'amount'=>0,'product_upload'=>1],['id'=>2,'amount'=>100,'product_upload'=>3]]);
        $user=$this->account();
        $this->assertEquals(2,$user->customer_package_id); $this->assertEquals(3,$user->remaining_uploads);
        DB::table('customer_products')->insert(['user_id'=>$user->id]);
        $user->customer_package_id=1; $user->balance=17; $user->save();
        $this->assertEquals(2,$user->fresh()->customer_package_id); $this->assertEquals(2,$user->fresh()->remaining_uploads);
        $this->assertEquals(17,(int)$user->fresh()->balance);
        DB::table('customer_products')->insert([['user_id'=>$user->id],['user_id'=>$user->id]]);
        $this->assertFalse(app(AccountMembership::class)->canUploadClassified($user));
    }

    public function testDataMigrationUpdatesExistingAccountsWithoutTouchingWalletsOrApproval()
    {
        $seller=$this->seller(); $customer=$this->account(); $admin=$this->account('admin');
        DB::table('shops')->update(['seller_package_id'=>4,'product_upload_limit'=>10,'package_invalid_at'=>'2020-01-01']);
        DB::table('customer_packages')->insert(['id'=>2,'amount'=>100,'product_upload'=>5]);
        DB::table('customer_products')->insert(['user_id'=>$customer->id]);
        DB::table('users')->update(['balance'=>87]);
        require_once database_path('migrations/2026_10_21_160000_default_highest_membership.php');
        $migration=new \DefaultHighestMembership; $migration->up(); $migration->up();
        $this->assertDatabaseHas('shops',['user_id'=>$seller->id,'seller_package_id'=>6,'product_upload_limit'=>2000,'package_invalid_at'=>'9999-12-31','verification_status'=>0]);
        $this->assertDatabaseHas('users',['id'=>$customer->id,'customer_package_id'=>2,'remaining_uploads'=>4]);
        $this->assertNull($admin->fresh()->customer_package_id);
        $this->assertEquals(3,DB::table('users')->where('balance',87)->count());
        $this->assertEquals(6,DB::table('seller_packages')->where('is_default',1)->value('id'));
    }

    public function testLegacyExpiryJobsCannotUnpublishExistingProducts()
    {
        $user=$this->seller(); DB::table('products')->insert(['user_id'=>$user->id]);
        DB::table('shops')->update(['package_invalid_at'=>'2020-01-01']);
        app(\App\Http\Controllers\SellerPackageController::class)->unpublish_products(Request::create('/'));
        app(\App\Http\Controllers\SellerSpreadPackageController::class)->unpublish_products(Request::create('/'));
        $this->assertEquals(1,DB::table('products')->value('published'));
    }

    public function testRealSellerPurchaseEndpointsAreRetiredBeforeWalletActions()
    {
        $seller=$this->seller();
        $this->actingAs($seller);
        foreach (['/seller/seller-packages','/seller/seller-spread-packages','/seller/affiliate'] as $url) {
            $this->getJson($url)->assertNotFound();
        }
        foreach (['/seller_packages/buy_package_by_cash','/seller_packages/buy_spread_cash',
            '/seller/products/spread','/seller/offline-seller-package-paymnet','/seller/offline-seller-spread-package-paymnet'] as $url) {
            $this->postJson($url,['package_id'=>4])->assertNotFound();
        }
        $this->assertEquals(0,(int)$seller->fresh()->balance);
    }

    public function testSellerCannotReopenDistributionThroughTheBuyerAffiliateRoutes()
    {
        $route=(new Route('GET','/',function(){}))->name('affiliate.user.index');
        $request=Request::create('/'); $request->setRouteResolver(function()use($route){return $route;});
        $customer=$this->account(); $request->setUserResolver(function()use($customer){return $customer;});
        $middleware=new RetiredMembershipFeatures;
        $this->assertEquals('retained',$middleware->handle($request,function(){return 'retained';}));
        $seller=$this->account('seller'); $request->setUserResolver(function()use($seller){return $seller;});
        $this->expectException(HttpException::class);
        $middleware->handle($request,function(){return 'unexpected';});
    }

    public function testRetiredRoutesBlockPurchasesWhileCustomerAffiliateAndAdminManagementRemain()
    {
        $middleware=new RetiredMembershipFeatures;
        $names=['seller.seller_packages_list','seller.packages_payment_list','seller_packages.purchase','orders.buy_package_cash',
            'seller.seller_spread_packages_list','seller.spread_packages_payment_list','seller_spread_packages.purchase','orders.buy_spread_cash',
            'seller.products.spread','seller.affiliate.index','customer_packages_list_show','customer_packages.purchase',
            'seller.make_offline_payment','seller.spread_make_offline_payment','sellers.setpackage','customer_package.make_offline_payment',
            'offline_customer_package_purchase_modal','seller.offline_seller_package_purchase_modal','seller.offline_seller_spread_package_purchase_modal',
            'seller_packages.unpublish_products','seller_spread_packages.unpublish_products'];
        foreach ($names as $name) {
            $request=Request::create('/'); $route=(new Route(['GET','POST'],'/',function(){}))->name($name);
            $request->setRouteResolver(function()use($route){return $route;});
            try { $middleware->handle($request,function(){return 'unexpected';}); $this->fail($name); }
            catch (HttpException $e) { $this->assertEquals(404,$e->getStatusCode(),$name); }
        }
        foreach (['affiliate.user.index','affiliate.withdraw_request.store','affiliate.index','seller_packages.index','customer_packages.index','seller.orders.index'] as $name) {
            $route=(new Route('GET','/',function(){}))->name($name);
            $request->setRouteResolver(function()use($route){return $route;});
            $this->assertEquals('retained',$middleware->handle($request,function(){return 'retained';}),$name);
        }
    }

    public function testSellerPagesNoLongerPromptForPaidUpgradesOrRetiredFeatures()
    {
        foreach (['seller/dashboard.blade.php','seller/inc/seller_sidenav.blade.php','seller/product/products/index.blade.php','seller/product/digitalproducts/index.blade.php'] as $view) {
            $source=file_get_contents(resource_path('views/'.$view));
            foreach (['Please upgrade your package.','seller.seller_packages_list','seller.seller_spread_packages_list','seller.affiliate.index'] as $retired) {
                $this->assertStringNotContainsString($retired,$source,$view);
            }
        }
    }

    public function testFundReleaseStopsSellerUplineRewardsButKeepsCustomerThreeLevelRewards()
    {
        Schema::create('orders',function(Blueprint $t){
            $t->increments('id'); $t->integer('seller_id'); $t->integer('user_id'); $t->decimal('grand_total',20,2);
            $t->decimal('product_storehouse_total',20,2)->default(0); $t->boolean('picking_switch')->default(1);
            $t->timestamp('freeze_expired_at')->nullable(); $t->timestamps();
        });
        Schema::create('affiliate_logs',function(Blueprint $t){
            $t->increments('id'); $t->integer('user_id'); $t->integer('referred_by_user'); $t->decimal('amount',20,2);
            $t->integer('order_id'); $t->string('affiliate_type'); $t->timestamps();
        });
        Schema::create('business_settings',function(Blueprint $t){$t->increments('id');$t->string('type');$t->string('value');$t->string('lang')->nullable();});
        foreach ([1=>10,2=>5,3=>1] as $level=>$ratio) {DB::table('business_settings')->insert(['type'=>'commission_ratio_level_'.$level,'value'=>$ratio]);}
        $seller3=$this->account('seller'); $seller2=$this->account('seller',$seller3->id); $seller1=$this->account('seller',$seller2->id);
        $seller=$this->seller(); $seller->pid=$seller1->id; $seller->save();
        $customer3=$this->account(); $customer2=$this->account('customer',$customer3->id); $customer1=$this->account('customer',$customer2->id);
        $buyer=$this->account('customer',$customer1->id);
        DB::table('shops')->where('user_id',$seller->id)->update(['admin_to_pay'=>100]);
        $id=DB::table('orders')->insertGetId(['seller_id'=>$seller->id,'user_id'=>$buyer->id,'grand_total'=>100,'freeze_expired_at'=>now()]);
        $this->assertTrue(product_storehouse_order_free_up($id));
        $this->assertEquals(100,(int)$seller->fresh()->balance);
        foreach ([$seller1,$seller2,$seller3] as $u) {$this->assertEquals(0,(int)$u->fresh()->balance);}
        $this->assertEquals(10,(int)$customer1->fresh()->balance); $this->assertEquals(5,(int)$customer2->fresh()->balance); $this->assertEquals(1,(int)$customer3->fresh()->balance);
        $this->assertEquals(3,DB::table('affiliate_logs')->count());
        $this->assertEquals(0,DB::table('affiliate_logs')->where('user_id',$seller->id)->count());
        $this->assertNull(DB::table('orders')->where('id',$id)->value('freeze_expired_at'));
        $this->assertEquals(0,(int)$seller->shop->fresh()->admin_to_pay);
    }
}
