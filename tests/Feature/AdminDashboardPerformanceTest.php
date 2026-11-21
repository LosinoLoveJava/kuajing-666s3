<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminDashboardPerformanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array']);
        DB::purge('sqlite');
        Cache::flush();
        Schema::create('addons', function (Blueprint $t) { $t->increments('id'); });
        Schema::create('categories', function (Blueprint $t) {
            $t->increments('id'); $t->integer('parent_id')->default(0); $t->integer('level')->default(0);
        });
        Schema::create('category_translations', function (Blueprint $t) {
            $t->increments('id'); $t->integer('category_id'); $t->string('lang'); $t->string('name');
        });
        Schema::create('products', function (Blueprint $t) {
            $t->increments('id'); $t->integer('category_id'); $t->integer('num_of_sale')->nullable();
            $t->integer('published')->default(1); $t->string('added_by')->default('admin');
        });
        Schema::create('product_stocks', function (Blueprint $t) {
            $t->increments('id'); $t->integer('product_id'); $t->integer('qty');
        });
    }

    private function graph()
    {
        return app(AdminController::class)->admin_dashboard(Request::create('/admin'))->getData()['cached_graph_data'];
    }

    public function testTotalsIncludeDescendantsAndUnpublishedProductsWithoutMultiplyingSalesByVariants()
    {
        DB::table('categories')->insert([
            ['id' => 1, 'parent_id' => 0, 'level' => 0],
            ['id' => 2, 'parent_id' => 1, 'level' => 1],
            ['id' => 3, 'parent_id' => 2, 'level' => 2],
            ['id' => 4, 'parent_id' => 0, 'level' => 0],
            ['id' => 5, 'parent_id' => 0, 'level' => 0],
        ]);
        DB::table('products')->insert([
            ['id' => 1, 'category_id' => 1, 'num_of_sale' => 3, 'published' => 1, 'added_by' => 'admin'],
            ['id' => 2, 'category_id' => 2, 'num_of_sale' => 7, 'published' => 0, 'added_by' => 'seller'],
            ['id' => 3, 'category_id' => 3, 'num_of_sale' => 11, 'published' => 1, 'added_by' => 'seller'],
            ['id' => 4, 'category_id' => 4, 'num_of_sale' => 13, 'published' => 1, 'added_by' => 'admin'],
            ['id' => 5, 'category_id' => 999, 'num_of_sale' => 100, 'published' => 1, 'added_by' => 'admin'],
        ]);
        DB::table('product_stocks')->insert([
            ['product_id' => 1, 'qty' => 2], ['product_id' => 1, 'qty' => 5],
            ['product_id' => 2, 'qty' => 17], ['product_id' => 4, 'qty' => 19],
            ['product_id' => 5, 'qty' => 100], ['product_id' => 999, 'qty' => 100],
        ]);
        Cache::put('cached_graph_data', ['num_of_sale_data' => 'stale', 'qty_data' => 'stale']);
        $this->assertSame(['num_of_sale_data' => '21,13,0,', 'qty_data' => '24,19,0,'], $this->graph());
    }

    public function testColdCacheQueriesStayBoundedAndNeverLoadProductModelsOrRelations()
    {
        DB::table('categories')->insert(['id' => 1]);
        $products = [];
        for ($id = 1; $id <= 5000; ++$id) {
            $products[] = ['id' => $id, 'category_id' => 1, 'num_of_sale' => 2];
        }
        foreach (array_chunk($products, 200) as $chunk) {
            DB::table('products')->insert($chunk);
        }
        DB::enableQueryLog();
        $this->assertSame(['num_of_sale_data' => '10000,', 'qty_data' => '0,'], $this->graph());
        $queries = DB::getQueryLog();
        $this->assertCount(6, $queries);
        foreach ($queries as $query) {
            if (strpos($query['query'], '"products"') !== false) {
                $this->assertStringContainsString('SUM(', $query['query']);
                $this->assertStringContainsString('group by', $query['query']);
            }
        }
        DB::flushQueryLog();
        $this->graph();
        $this->assertCount(3, DB::getQueryLog());
    }

    public function testEmptyCatalogueReturnsEmptyGraphData()
    {
        $this->assertSame(['num_of_sale_data' => '', 'qty_data' => ''], $this->graph());
    }
}
