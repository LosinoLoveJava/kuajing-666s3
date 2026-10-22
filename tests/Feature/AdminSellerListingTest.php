<?php

namespace Tests\Feature;

use App\Http\Controllers\SellerController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminSellerListingTest extends TestCase
{
    private $viewDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('user_type')->default('seller');
            $t->string('email')->nullable(); $t->string('phone')->nullable(); $t->string('phone_normalized')->nullable();
            $t->integer('pid')->nullable(); $t->boolean('is_virtual')->default(0); $t->boolean('banned')->default(0);
            $t->decimal('balance', 20, 2)->default(0); $t->timestamps();
        });
        Schema::create('shops', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->string('name');
            $t->integer('verification_status')->default(0); $t->text('verification_info')->nullable();
            $t->decimal('admin_to_pay', 20, 2)->default(0); $t->integer('view_base_num')->default(0);
            $t->integer('view_inc_num')->default(0); $t->boolean('comment_permission')->default(0);
            $t->boolean('home_display')->default(0); $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->text('description')->nullable();
        });
        // Deliberately no product translation/tax tables: listing must never hydrate products.
        Schema::create('wallets', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->decimal('amount', 20, 2);
            $t->boolean('approval'); $t->string('payment_method')->nullable(); $t->timestamps();
        });
        Schema::create('seller_withdraw_requests', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->decimal('amount', 20, 2); $t->integer('status');
        });
        Schema::create('translations', function (Blueprint $t) {
            $t->increments('id'); $t->string('lang'); $t->string('lang_key'); $t->text('lang_value'); $t->timestamps();
        });
        Schema::create('business_settings', function (Blueprint $t) {
            $t->increments('id'); $t->string('type'); $t->string('value');
        });
        foreach (['decimal_separator' => 1, 'no_of_decimals' => 2, 'symbol_format' => 1] as $key => $value) {
            DB::table('business_settings')->insert(['type' => $key, 'value' => $value]);
        }
        session()->put('currency_symbol', '$');
        $this->viewDirectory = storage_path('framework/testing/admin-list-'.bin2hex(random_bytes(8)));
        mkdir($this->viewDirectory.'/backend/layouts', 0700, true);
        file_put_contents($this->viewDirectory.'/backend/layouts/app.blade.php', "@yield('content')\n@yield('script')");
        $finder = app('view')->getFinder();
        $finder->prependLocation($this->viewDirectory); $finder->flush();
        DB::table('users')->insert(['id' => 100, 'name' => '<script>Sales</script>', 'user_type' => 'salesman']);
    }

    protected function tearDown(): void
    {
        if ($this->viewDirectory) { (new \Illuminate\Filesystem\Filesystem)->deleteDirectory($this->viewDirectory); }
        parent::tearDown();
    }

    private function seller($id, array $user = [], array $shop = [])
    {
        DB::table('users')->insert(array_merge(['id' => $id, 'name' => 'Seller '.$id, 'pid' => 100], $user));
        DB::table('shops')->insert(array_merge(['id' => $id, 'user_id' => $id, 'name' => 'Shop '.$id,
            'created_at' => '2026-10-22 23:59:59'], $shop));
    }

    private function listing(array $filters = [])
    {
        return app(SellerController::class)->index(Request::create('/admin/sellers', 'GET', $filters));
    }

    public function testDefaultPageRendersWithMissingParentAndEmptyLedger()
    {
        $this->seller(1, ['pid' => 999]);
        $view = $this->listing();
        $data = $view->getData();
        foreach (['date', 'user_id', 'is_virtual_user', 'approved', 'sort_search'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
        $html = $view->render();
        $this->assertStringContainsString('Shop 1', $html);
        $this->assertStringContainsString('---', $html);
        $this->assertStringContainsString('set_pid(1,999)', $html);
        $this->assertStringNotContainsString('<script>Sales</script>', $html);
        $this->assertEquals(0, $data['shops']->first()->user->products_count);
    }

    public function testAggregatesKeepLedgerRulesWithoutHydratingCollections()
    {
        $this->seller(1); $this->seller(2, ['pid' => null]);
        DB::table('products')->insert([['user_id' => 1], ['user_id' => 1], ['user_id' => 2]]);
        foreach ([[1, 100, 1, 'bank'], [1, 25, 1, null], [1, 900, 1, 'seller_order_reward'],
            [1, 400, 0, 'bank'], [2, 700, 1, 'bank']] as $row) {
            DB::table('wallets')->insert(array_combine(['user_id', 'amount', 'approval', 'payment_method'], $row));
        }
        foreach ([[1, 20, 1], [1, 300, 0], [2, 100, 1]] as $row) {
            DB::table('seller_withdraw_requests')->insert(array_combine(['user_id', 'amount', 'status'], $row));
        }
        $view = $this->listing();
        $user = $view->getData()['shops']->firstWhere('user_id', 1)->user;
        $this->assertEquals(2, $user->products_count);
        $this->assertEquals(125, $user->recharge_total);
        $this->assertEquals(20, $user->withdrawal_total);
        $html = $view->render();
        $this->assertStringContainsString('$105.00', $html);
        $this->assertStringContainsString('$825.00', $html); // Page-only recharge total.
        foreach (['products', 'wallets', 'seller_withdraw_requests'] as $relation) {
            $this->assertFalse($user->relationLoaded($relation));
        }
    }

    public function testExistingFiltersComposeAndPreserveZeroValues()
    {
        $this->seller(1, ['phone_normalized' => '+971501234567']);
        $this->seller(2, ['is_virtual' => 1]);
        $this->seller(3, [], ['verification_status' => 1]);
        $this->seller(4, ['pid' => 999]);
        $this->seller(5, [], ['created_at' => '2026-10-23 00:00:00']);
        DB::table('shops')->insert(['id' => 6, 'user_id' => 9999, 'name' => 'Orphan']);
        $filters = ['search' => '501234567', 'approved_status' => '0', 'is_virtual_user' => '0',
            'user_id' => '100', 'date' => '2026-10-22 to 2026-10-22'];
        $data = $this->listing($filters)->getData();
        $this->assertEquals([1], $data['shops']->pluck('id')->all());
        $this->assertSame('0', $data['approved']);
        $this->assertSame('0', $data['is_virtual_user']);
        foreach (['search', 'approved_status', 'is_virtual_user', 'user_id', 'date'] as $key) {
            $subset = $filters; unset($subset[$key]);
            $this->assertGreaterThanOrEqual(1, $this->listing($subset)->getData()['shops']->total());
        }
        $this->assertSame(5, $this->listing()->getData()['shops']->total());
        $this->assertEquals([2], $this->listing(['is_virtual_user' => '1'])->getData()['shops']->pluck('id')->all());
        $this->assertEquals([3], $this->listing(['approved_status' => '1'])->getData()['shops']->pluck('id')->all());
        $this->assertEquals([4], $this->listing(['user_id' => '999'])->getData()['shops']->pluck('id')->all());
        $this->assertEquals([5], $this->listing(['date' => '2026-10-23 to 2026-10-23'])->getData()['shops']->pluck('id')->all());
        $this->assertMatchesRegularExpression('/value="0"\s+selected/', $this->listing(['approved_status' => '0'])->render());
    }

    public function testPaginationAndQueryCountStayBoundedAsCatalogueGrows()
    {
        for ($id = 1; $id <= 18; ++$id) { $this->seller($id); }
        DB::enableQueryLog(); DB::flushQueryLog();
        $initial = $this->listing()->getData()['shops'];
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($batch = 0; $batch < 10; ++$batch) {
            DB::table('products')->insert(array_fill(0, 100, ['user_id' => 18, 'description' => str_repeat('x', 4096)]));
        }
        DB::enableQueryLog(); DB::flushQueryLog();
        $page = $this->listing()->getData()['shops'];
        $this->assertSame($queries, count(DB::getQueryLog()));
        $this->assertLessThanOrEqual(5, $queries);
        $this->assertCount(15, $page);
        $this->assertSame(18, $page->total());
        $this->assertEquals(1000, $page->first()->user->products_count);
        $this->assertEquals($initial->pluck('id')->all(), $page->pluck('id')->all());
        $this->assertFalse($page->first()->user->relationLoaded('products'));
    }

    public function testEmptyResultRendersWithoutMissingVariables()
    {
        $view = $this->listing(['search' => 'nonexistent']);
        $this->assertSame(0, $view->getData()['shops']->total());
        $this->assertStringContainsString('All Sellers', $view->render());
    }

    /** @dataProvider invalidFilters */
    public function testInvalidFiltersRaiseValidationInsteadOfServerErrors(array $filters)
    {
        $this->expectException(ValidationException::class);
        $this->listing($filters);
    }

    public static function invalidFilters()
    {
        return [[['date' => 'invalid']], [['date' => '2026-02-30 to 2026-03-01']],
            [['date' => '2026-10-23 to 2026-10-22']], [['search' => ['bad']]],
            [['approved_status' => 9]], [['is_virtual_user' => ['0']]], [['user_id' => -1]]];
    }
}
