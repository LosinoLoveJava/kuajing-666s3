<?php

namespace Tests\Feature;

use App\Http\Controllers\Seller\DashboardController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerDashboardPerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        DB::connection()->getPdo()->sqliteCreateFunction('DATE_FORMAT', function ($date, $format) {
            return date($format === '%Y-%m-%d' ? 'Y-m-d' : 'd M', strtotime($date));
        });
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('user_type'); });
        DB::table('users')->insert([['id' => 1, 'user_type' => 'seller'], ['id' => 2, 'user_type' => 'seller']]);
        $this->actingAs(User::findOrFail(1));
        Schema::create('shops', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->integer('verification_status');
        });
        DB::table('shops')->insert(['user_id' => 1, 'verification_status' => 1]);
        Schema::create('business_settings', function (Blueprint $t) { $t->increments('id'); $t->string('type'); $t->string('value'); });
        DB::table('business_settings')->insert(['type' => 'vendor_system_activation', 'value' => '1']);
        Schema::create('categories', function (Blueprint $t) { $t->increments('id'); $t->string('name'); });
        Schema::create('category_translations', function (Blueprint $t) {
            $t->increments('id'); $t->integer('category_id'); $t->string('lang'); $t->string('name');
        });
        Schema::create('products', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->integer('category_id');
            $t->string('added_by')->default('seller'); $t->integer('approved')->default(1);
            $t->integer('published')->default(1); $t->integer('auction_product')->default(0);
            $t->integer('num_of_sale')->default(0); $t->timestamps();
        });
        Schema::create('product_translations', function (Blueprint $t) {
            $t->increments('id'); $t->integer('product_id'); $t->string('lang');
        });
        Schema::create('product_taxes', function (Blueprint $t) { $t->increments('id'); $t->integer('product_id'); });
        Schema::create('orders', function (Blueprint $t) {
            $t->increments('id'); $t->integer('seller_id'); $t->string('payment_status');
            $t->string('delivery_status'); $t->decimal('grand_total', 20, 2);
            $t->decimal('product_storehouse_total', 20, 2)->nullable(); $t->timestamps();
        });
        Schema::create('order_details', function (Blueprint $t) {
            $t->increments('id'); $t->integer('seller_id'); $t->integer('order_id'); $t->string('delivery_status');
        });
        Schema::create('refund_requests', function (Blueprint $t) {
            $t->increments('id'); $t->integer('order_detail_id'); $t->integer('refund_status');
        });
        Schema::create('notifications', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('type'); $t->string('notifiable_type');
            $t->integer('notifiable_id'); $t->text('data'); $t->timestamp('read_at')->nullable(); $t->timestamps();
        });
        Schema::create('conversations', function (Blueprint $t) {
            $t->increments('id'); $t->integer('sender_id'); $t->integer('receiver_id');
            $t->integer('sender_viewed')->default(0); $t->integer('receiver_viewed')->default(0);
            $t->integer('is_tip')->default(0); $t->timestamps();
        });
        Schema::create('messages', function (Blueprint $t) {
            $t->increments('id'); $t->integer('conversation_id'); $t->integer('user_id')->nullable(); $t->timestamps();
        });
        require_once database_path('migrations/2026_10_22_140000_add_seller_dashboard_performance_indexes.php');
    }

    private function categoriesAndProducts()
    {
        DB::table('categories')->insert([['id' => 1, 'name' => 'Shared'], ['id' => 2, 'name' => 'Own'], ['id' => 3, 'name' => 'Other']]);
        DB::table('category_translations')->insert(['category_id' => 1, 'lang' => 'cn', 'name' => '共享分类']);
        DB::table('products')->insert([
            ['user_id' => 1, 'category_id' => 1, 'published' => 1],
            ['user_id' => 1, 'category_id' => 1, 'published' => 0],
            ['user_id' => 1, 'category_id' => 2, 'published' => 1],
            ['user_id' => 2, 'category_id' => 1, 'published' => 1],
            ['user_id' => 2, 'category_id' => 3, 'published' => 1],
            ['user_id' => 1, 'category_id' => 999, 'published' => 1], // orphan category stays hidden
        ]);
    }

    public function testCategoryCountsAreSellerScopedKeepUnpublishedProductsAndTranslations()
    {
        $this->categoriesAndProducts();
        app()->setLocale('cn');
        $data = app(DashboardController::class)->index(Request::create('/seller/dashboard?user_id=2'))->getData();
        $this->assertEquals([1 => 2, 2 => 1], $data['product_categories']->pluck('product_count', 'id')->all());
        $this->assertSame('共享分类', $data['product_categories']->first()->getTranslation('name'));
        foreach ($data['product_categories'] as $category) {
            $this->assertFalse($category->relationLoaded('products'));
        }
    }

    public function testQueryCountDoesNotGrowWithUnrelatedCatalogue()
    {
        $this->categoriesAndProducts();
        DB::enableQueryLog();
        app(DashboardController::class)->index(Request::create('/seller/dashboard'));
        $initialQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($id = 10; $id < 210; ++$id) {
            DB::table('categories')->insert(['id' => $id, 'name' => 'Unrelated']);
            DB::table('products')->insert(['category_id' => $id, 'user_id' => 2]);
        }
        DB::enableQueryLog(); DB::flushQueryLog();
        $data = app(DashboardController::class)->index(Request::create('/seller/dashboard'))->getData();
        $queries = DB::getQueryLog();
        $this->assertLessThanOrEqual($initialQueries, count($queries));
        $this->assertLessThanOrEqual(12, count($queries));
        $this->assertCount(2, $data['product_categories']);
        $this->assertCount(3, $data['products']);
    }

    public function testSellerWithoutProductsGetsAnEmptyCategoryList()
    {
        $this->assertCount(0, app(DashboardController::class)->index(Request::create('/seller/dashboard'))->getData()['product_categories']);
    }

    public function testSharedNavigationOnlyQueriesTwentyNewestUnreadNotificationsForCurrentSeller()
    {
        for ($id = 1; $id <= 35; ++$id) {
            DB::table('notifications')->insert(['id' => (string) $id, 'type' => 'Fixture', 'notifiable_type' => User::class,
                'notifiable_id' => $id > 30 ? 2 : 1, 'data' => '{}', 'read_at' => $id === 30 ? now() : null,
                'created_at' => now()->addSeconds($id), 'updated_at' => now()]);
        }
        DB::enableQueryLog();
        $view = view('seller.layouts.app');
        view()->callComposer($view);
        $notifications = $view->getData()['sellerUnreadNotifications'];
        $this->assertCount(20, $notifications);
        $this->assertEquals(range(29, 10), $notifications->pluck('id')->all());
        $this->assertCount(1, DB::getQueryLog());
        $this->assertStringContainsString('limit 20', DB::getQueryLog()[0]['query']);
        $this->assertFalse(auth()->user()->relationLoaded('unreadNotifications'));
    }

    public function testMessageCountUsesBoundedAggregateQueriesAndPreservesUnreadRules()
    {
        DB::table('conversations')->insert([
            ['id' => 1, 'sender_id' => 1, 'receiver_id' => 2, 'sender_viewed' => 0, 'receiver_viewed' => 1],
            ['id' => 2, 'sender_id' => 2, 'receiver_id' => 1, 'sender_viewed' => 1, 'receiver_viewed' => 0],
            ['id' => 3, 'sender_id' => 1, 'receiver_id' => 1, 'sender_viewed' => 0, 'receiver_viewed' => 0],
            ['id' => 4, 'sender_id' => 2, 'receiver_id' => 3, 'sender_viewed' => 0, 'receiver_viewed' => 0],
        ]);
        $time = '2026-10-22 12:00:00';
        foreach ([[1, 2, $time, $time], [1, 1, $time, $time], [2, 2, $time, '2026-10-22 12:01:00'],
            [2, 2, $time, $time], [4, 2, $time, $time], [3, null, null, null]] as $row) {
            DB::table('messages')->insert(array_combine(['conversation_id', 'user_id', 'created_at', 'updated_at'], $row));
        }
        DB::enableQueryLog();
        $result = app(\App\Http\Controllers\Seller\ConversationController::class)->message_count(Request::create('/'));
        $this->assertSame(['result' => 7], $result->getData(true));
        $this->assertCount(3, DB::getQueryLog());
    }

    public function testNewMessagePollingHandlesEmptyInboxAndAcknowledgesOnlyTheCurrentReceiver()
    {
        $controller = app(\App\Http\Controllers\ConversationController::class);
        $this->assertSame(['code' => 0, 'msg' => 'No'], $controller->check_new_msg()->getData(true));
        DB::table('conversations')->insert([
            ['id' => 1, 'sender_id' => 1, 'receiver_id' => 2],
            ['id' => 2, 'sender_id' => 2, 'receiver_id' => 1],
        ]);
        $this->assertSame(['code' => 1, 'msg' => 'Yes'], $controller->check_new_msg()->getData(true));
        $this->assertSame(['code' => 0, 'msg' => 'No'], $controller->check_new_msg()->getData(true));
        $this->assertEquals(0, DB::table('conversations')->where('id', 1)->value('is_tip'));
        $this->assertEquals(1, DB::table('conversations')->where('id', 2)->value('is_tip'));
    }

    public function testIndexMigrationIsRepeatableAndRollbackPreservesPreexistingIndexesAndData()
    {
        $this->categoriesAndProducts();
        Schema::table('products', function (Blueprint $t) { $t->index(['user_id', 'category_id', 'id'], 'existing_seller_index'); });
        $migration = new \AddSellerDashboardPerformanceIndexes;
        $migration->up(); $migration->up();
        $names = array_column(DB::select('PRAGMA index_list("products")'), 'name');
        $this->assertContains('existing_seller_index', $names);
        $this->assertNotContains('seller_perf_products_user_category', $names);
        $this->assertContains('seller_perf_products_category', $names);
        $this->assertCount(6, DB::table('products')->get());
        $migration->down(); $migration->down();
        $this->assertSame(['existing_seller_index'], array_column(DB::select('PRAGMA index_list("products")'), 'name'));
        $this->assertDatabaseCount('products', 6);
    }
}
