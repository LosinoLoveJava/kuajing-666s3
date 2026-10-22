<?php

namespace Tests\Feature;

use App\Http\Controllers\ProductStorehouseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Seller\CommissionHistoryController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminDisplayTest extends TestCase
{
    use \Tests\Concerns\WalletDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWalletDatabase();
        Schema::create('orders', function (Blueprint $t) { $t->increments('id'); $t->string('code'); });
        Schema::create('commission_histories', function (Blueprint $t) {
            $t->increments('id'); $t->integer('order_id'); $t->integer('seller_id');
            $t->decimal('admin_commission', 20, 2); $t->decimal('seller_earning', 20, 2); $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('slug')->default('test-product');
            $t->string('added_by')->default('admin'); $t->integer('in_storehouse')->default(1);
            $t->integer('auction_product')->default(0); $t->integer('wholesale_product')->default(0);
            $t->integer('digital')->default(0); $t->integer('rating')->default(0);
            $t->integer('num_of_sale')->default(0); $t->decimal('unit_price', 20, 2)->default(10); $t->timestamps();
        });
        Schema::create('product_stocks', function (Blueprint $t) {
            $t->increments('id'); $t->integer('product_id'); $t->string('sku'); $t->integer('qty')->default(1);
        });
        Schema::create('product_translations', function (Blueprint $t) {
            $t->increments('id'); $t->integer('product_id'); $t->string('lang'); $t->string('name');
        });
        Schema::create('product_taxes', function (Blueprint $t) { $t->increments('id'); $t->integer('product_id'); });
        Schema::create('addons', function (Blueprint $t) { $t->increments('id'); });
        DB::table('users')->insert([
            ['id'=>1, 'name'=>'Admin', 'user_type'=>'admin'],
            ['id'=>2, 'name'=>'Seller A', 'user_type'=>'seller'],
            ['id'=>3, 'name'=>'Seller B', 'user_type'=>'seller'],
        ]);
        Auth::setUser(User::findOrFail(1));
        foreach (['decimal_separator'=>1, 'no_of_decimals'=>2, 'symbol_format'=>1] as $key=>$value) {
            DB::table('business_settings')->insert(['type'=>$key, 'value'=>$value]);
        }
        session(['currency_exchange_rate'=>1, 'currency_symbol'=>'$']);
        app('view')->getFinder()->prependLocation(base_path('tests/Fixtures/views'));
        app('view')->getFinder()->flush();
    }

    private function request(array $filters = [], $page = 1)
    {
        $request = Request::create('/admin/report', 'GET', $filters + ['page'=>$page]);
        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);
        return $request;
    }

    private function ledger($date, $user = 2)
    {
        DB::table('wallets')->insert(['user_id'=>$user, 'amount'=>12.34, 'payment_method'=>'bank', 'created_at'=>$date]);
        DB::table('commission_histories')->insert(['seller_id'=>$user, 'order_id'=>99,
            'admin_commission'=>1.23, 'seller_earning'=>11.11, 'created_at'=>$date]);
    }

    public function testSingleDayIncludesMidnightAndLastSecondButExcludesAdjacentDays()
    {
        foreach (['2026-10-20 23:59:59', '2026-10-21 00:00:00', '2026-10-21 12:34:56',
            '2026-10-21 23:59:59', '2026-10-22 00:00:00'] as $date) { $this->ledger($date); }
        $filters = ['date_range'=>'2026-10-21 / 2026-10-21'];
        $wallets = app(ReportController::class)->wallet_transaction_history($this->request($filters))->getData()['wallets'];
        $commissions = app(ReportController::class)->commission_history($this->request($filters))->getData()['commission_history'];
        $this->assertEquals([4,3,2], $wallets->pluck('id')->all());
        $this->assertEquals([4,3,2], $commissions->pluck('id')->all());
        $this->assertTrue($wallets->first()->relationLoaded('user'));
        $this->assertTrue($commissions->first()->relationLoaded('order'));
        $this->assertEquals(5, DB::table('wallets')->count());
        $this->assertEquals(61.70, DB::table('wallets')->sum('amount'));
    }

    public function testReportFiltersSurvivePaginationAndDeletedRelationsStillRender()
    {
        for ($i=0; $i<12; ++$i) { $this->ledger('2026-10-21 17:10:00'); }
        $this->ledger('2026-10-21 17:10:00', 3);
        $this->ledger('2026-10-22 17:10:00');
        $filters = ['date_range'=>'2026-10-21 / 2026-10-21', 'user_id'=>2, 'seller_id'=>2];
        foreach (['wallet_transaction_history'=>'wallets', 'commission_history'=>'commission_history'] as $method=>$key) {
            $view = app(ReportController::class)->$method($this->request($filters, 2));
            $page = $view->getData()[$key];
            $this->assertSame(12, $page->total());
            $this->assertEquals([2,1], $page->pluck('id')->all());
            parse_str(parse_url($page->url(1), PHP_URL_QUERY), $query);
            foreach ($filters as $field=>$value) { $this->assertEquals($value, $query[$field]); }
            $html = $view->render();
            $this->assertStringContainsString('<td>11</td>', $html);
            if ($key === 'commission_history') { $this->assertStringContainsString('Order Deleted', $html); }
        }
    }

    /** @dataProvider invalidDateRanges */
    public function testInvalidDatesReturnValidationErrorsInsteadOfEmptyLists($range)
    {
        $this->expectException(ValidationException::class);
        app(ReportController::class)->wallet_transaction_history($this->request(['date_range'=>$range]));
    }

    public function invalidDateRanges()
    {
        return [['2026-10-21'], ['2026-02-30 / 2026-03-01'], ['2026-10-22 / 2026-10-21'],
            ['2026-10-21 / 2026-10-21 / 2026-10-22'], [['2026-10-21', '2026-10-21']]];
    }

    public function testSellerCommissionUsesSameDateRuleAndCannotSelectAnotherSeller()
    {
        $this->ledger('2026-10-21 23:59:59', 2); $this->ledger('2026-10-21 23:59:59', 3);
        Auth::setUser(User::findOrFail(2));
        $filters = ['date_range'=>'2026-10-21 / 2026-10-21', 'seller_id'=>3];
        foreach ([app(ReportController::class)->commission_history($this->request($filters)),
            app(CommissionHistoryController::class)->index($this->request($filters))] as $view) {
            $this->assertSame('seller.commission_history.index', $view->getName());
            $this->assertEquals([2], $view->getData()['commission_history']->pluck('seller_id')->all());
        }
    }

    private function product(array $attributes = [], $sku = 'MATCH-SKU')
    {
        $id = DB::table('products')->insertGetId($attributes + ['name'=>'Test product', 'created_at'=>'2026-10-21 12:00:00']);
        DB::table('product_stocks')->insert(['product_id'=>$id, 'sku'=>$sku]);
        return $id;
    }

    public function testWarehouseSearchKeepsOwnershipAndProductTypeRestrictions()
    {
        $valid = $this->product();
        foreach ([['added_by'=>'seller'], ['in_storehouse'=>0], ['digital'=>1], ['auction_product'=>1],
            ['wholesale_product'=>1]] as $attributes) { $this->product($attributes); }
        $this->product(['name'=>'MATCH-SKU outside warehouse', 'in_storehouse'=>0], 'OTHER');
        $view = app(ProductStorehouseController::class)->index($this->request(['search'=>'MATCH-SKU']));
        $products = $view->getData()['products'];
        $this->assertEquals([$valid], $products->pluck('id')->all());
        $this->assertTrue($products->first()->relationLoaded('stocks'));
    }

    public function testWarehouseSortAndPaginationRemainStable()
    {
        for ($i=1; $i<=17; ++$i) { $this->product(['name'=>'Matching product '.$i, 'unit_price'=>$i]); }
        $filters = ['search'=>'Matching', 'type'=>'unit_price,asc'];
        $products = app(ProductStorehouseController::class)->index($this->request($filters, 2))->getData()['products'];
        $this->assertSame(17, $products->total());
        $this->assertEquals([16,17], $products->pluck('id')->all());
        parse_str(parse_url($products->url(1), PHP_URL_QUERY), $query);
        $this->assertEquals('unit_price,asc', $query['type']);
        $this->assertEquals('Matching', $query['search']);
    }

    public function testUnsupportedWarehouseSortIsRejectedBeforeQuery()
    {
        $this->expectException(ValidationException::class);
        app(ProductStorehouseController::class)->index($this->request(['type'=>'name,invalid']));
    }

    public function testEmptyReportsRenderReadableMessagesWithoutJavascript()
    {
        foreach ([app(ReportController::class)->wallet_transaction_history($this->request()),
            app(ReportController::class)->commission_history($this->request()),
            app(ProductStorehouseController::class)->index($this->request())] as $view) {
            $html = $view->render();
            $this->assertStringContainsString('Nothing found', $html);
            $this->assertStringContainsString('aiz-table-scroll', $html);
        }
    }
}
