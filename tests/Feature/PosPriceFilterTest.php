<?php

namespace Tests\Feature;

use App\Http\Controllers\PosController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PosPriceFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('user_type'); $t->integer('pid')->nullable();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->increments('id'); $t->string('name')->default('Test product'); $t->string('barcode')->nullable();
            $t->integer('user_id')->default(2); $t->integer('category_id')->default(1); $t->integer('brand_id')->default(1);
            $t->integer('published')->default(1); $t->string('added_by')->default('seller');
            $t->integer('discount_start_date')->nullable(); $t->integer('discount_end_date')->nullable();
            $t->decimal('discount', 20, 2)->default(0); $t->string('discount_type')->default('amount');
            $t->timestamps();
        });
        Schema::create('product_stocks', function (Blueprint $t) {
            $t->increments('id'); $t->integer('product_id'); $t->decimal('price', 20, 2); $t->integer('qty')->default(100);
            $t->string('variant')->nullable(); $t->integer('image')->nullable();
        });
        Schema::create('product_taxes', function (Blueprint $t) {
            $t->increments('id'); $t->integer('product_id'); $t->string('tax_type'); $t->decimal('tax', 20, 2);
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->increments('id'); $t->integer('parent_id')->default(0); $t->integer('order_level')->default(0);
        });
        Schema::create('category_translations', function (Blueprint $t) {
            $t->increments('id'); $t->integer('category_id');
        });
        Schema::create('business_settings', function (Blueprint $t) {
            $t->increments('id'); $t->string('type'); $t->text('value')->nullable(); $t->string('lang')->nullable();
        });
        Schema::create('currencies', function (Blueprint $t) {
            $t->increments('id'); $t->string('code'); $t->string('symbol'); $t->decimal('exchange_rate', 15, 5);
        });
        DB::table('currencies')->insert(['id' => 1, 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1]);
        DB::table('business_settings')->insert([
            ['type' => 'system_default_currency', 'value' => '1'], ['type' => 'no_of_decimals', 'value' => '2'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'user_type' => 'admin', 'pid' => null],
            ['id' => 2, 'user_type' => 'seller', 'pid' => 4],
            ['id' => 3, 'user_type' => 'seller', 'pid' => null],
            ['id' => 4, 'user_type' => 'salesman', 'pid' => null],
        ]);
        $this->be(User::findOrFail(1));
    }

    private function product($price, array $attributes = [])
    {
        $id = DB::table('products')->insertGetId($attributes + ['created_at' => '2026-11-21 12:00:00']);
        return DB::table('product_stocks')->insertGetId(['product_id' => $id, 'price' => $price]);
    }

    private function search(array $filters = [])
    {
        $request = Request::create('/pos/products', 'GET', $filters);
        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);
        Paginator::currentPageResolver(function () use ($filters) { return $filters['page'] ?? 1; });
        return app(PosController::class)->search($request)->resource;
    }

    public function testDefaultSortMatchesDiscountsTaxesAndIndividualVariantPrices()
    {
        $this->product(100, ['discount_type' => 'percent', 'discount' => 90]); // 10
        $this->product(8); // 8
        $this->product(20, ['discount_type' => 'amount', 'discount' => 15]); // 5
        $this->product(2, ['discount' => 1, 'discount_start_date' => time()+3600, 'discount_end_date' => time()+7200]); // 2
        $this->product(3, ['discount' => 2, 'discount_start_date' => time()-7200, 'discount_end_date' => time()-3600]); // 3
        $this->product(4, ['discount' => 1, 'discount_start_date' => time()-3600, 'discount_end_date' => time()+3600]); // 3 -> 4.1 tax
        DB::table('product_taxes')->insert([
            ['product_id' => 6, 'tax_type' => 'percent', 'tax' => 10],
            ['product_id' => 6, 'tax_type' => 'percent', 'tax' => 10],
            ['product_id' => 6, 'tax_type' => 'amount', 'tax' => .5],
        ]);
        DB::table('product_stocks')->insert(['product_id' => 2, 'price' => 1.5]);
        $rows = $this->search();
        $this->assertEquals([7,4,5,6,3,2,1], $rows->pluck('stock_id')->all());
        $this->assertSame(7, $rows->total());
        $this->assertEquals([6], $this->search(['min_price' => '4.10', 'max_price' => '4.10'])->pluck('stock_id')->all());
    }

    public function testInclusivePriceRangeAndOneSidedBoundsUseDisplayedCurrencyAndRounding()
    {
        foreach ([0, 5, 10, 10.001, 15, 20] as $price) { $this->product($price); }
        session(['currency_code' => 'SGD', 'currency_exchange_rate' => 2]);
        $this->assertEquals([3,4,5], $this->search(['min_price' => '20', 'max_price' => '30'])->pluck('stock_id')->all());
        $this->assertEquals([1,2], $this->search(['max_price' => '10'])->pluck('stock_id')->all());
        $this->assertEquals([6], $this->search(['min_price' => '40'])->pluck('stock_id')->all());
        $this->assertEquals([1], $this->search(['min_price' => '0', 'max_price' => '0'])->pluck('stock_id')->all());
        $this->assertSame(0, $this->search(['min_price' => '1000'])->total());
    }

    public function testPaginationKeepsAllFiltersAndHasStableOrderingForEqualPrices()
    {
        for ($i = 1; $i <= 40; ++$i) { $this->product(10); }
        $filters = ['min_price' => '10', 'max_price' => '10', 'sort' => 'price_asc', 'user_id' => 2,
            'keyword' => 'Test', 'brand' => 1];
        $first = $this->search($filters);
        $second = $this->search($filters + ['page' => 2]);
        $this->assertSame(40, $first->total());
        $this->assertEquals(range(1,16), $first->pluck('stock_id')->all());
        $this->assertEquals(range(17,32), $second->pluck('stock_id')->all());
        $this->assertEmpty(array_intersect($first->pluck('stock_id')->all(), $second->pluck('stock_id')->all()));
        parse_str(parse_url($first->url(2), PHP_URL_QUERY), $query);
        foreach ($filters as $key => $value) {
            $this->assertEquals($value, $query[$key]);
        }
    }

    public function testBarcodeCannotBypassSellerBrandCategoryOrPriceFilters()
    {
        DB::table('categories')->insert(['id' => 1]);
        $this->product(10, ['barcode' => 'MATCH']);
        $this->product(10, ['barcode' => 'MATCH', 'user_id' => 3]);
        $this->product(100, ['barcode' => 'MATCH']);
        $this->product(10, ['barcode' => 'MATCH', 'brand_id' => 2]);
        $this->product(10, ['barcode' => 'MATCH', 'category_id' => 2]);
        $filters = ['keyword' => 'MATCH', 'category' => 'category-1', 'brand' => 1, 'max_price' => '20'];
        $this->assertEquals([1], $this->search($filters + ['user_id' => 2])->pluck('stock_id')->all());
        $this->be(User::findOrFail(4));
        $this->assertEquals([1], $this->search($filters)->pluck('stock_id')->all());
        $this->be(User::findOrFail(2));
        $this->assertEquals([1], $this->search($filters)->pluck('stock_id')->all());
        $this->assertSame(0, $this->search($filters + ['user_id' => 3])->total());
    }

    public function testNewestSortRemainsAvailableAndStillAppliesPrices()
    {
        $this->product(20, ['created_at' => '2026-11-20 12:00:00']);
        $this->product(10, ['created_at' => '2026-11-21 12:00:00']);
        $this->product(30, ['created_at' => '2026-11-22 12:00:00']);
        $this->assertEquals([3,2,1], $this->search(['sort' => 'newest'])->pluck('stock_id')->all());
        $this->assertEquals([2,1], $this->search(['sort' => 'newest', 'max_price' => '20'])->pluck('stock_id')->all());
    }

    /** @dataProvider invalidFilters */
    public function testInvalidFiltersAreRejectedBeforeQueryingProducts($filters)
    {
        $this->expectException(ValidationException::class);
        $this->search($filters);
    }

    public function invalidFilters()
    {
        return [[['min_price' => -1]], [['max_price' => -1]], [['min_price' => 'no']],
            [['max_price' => ['10']]], [['min_price' => 20, 'max_price' => 10]], [['sort' => 'price desc; DROP TABLE products']]];
    }
}
