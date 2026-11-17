<?php

namespace Tests\Feature;

use App\Http\Controllers\Seller\ProductStorehouseController;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerStorehouseBulkAddTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('user_type');
        });
        Schema::create('shops', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->integer('verification_status')->default(1);
            $t->integer('comment_permission')->default(0);
        });
        Schema::create('seller_packages', function (Blueprint $t) {
            $t->increments('id'); $t->integer('amount')->default(0);
            $t->integer('product_upload_limit')->default(1000); $t->integer('max_profit')->default(10);
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->increments('id'); $t->integer('parent_id')->default(0); $t->integer('order_level')->default(0);
        });
        Schema::create('category_translations', function (Blueprint $t) {
            $t->increments('id'); $t->integer('category_id'); $t->string('lang'); $t->string('name');
        });
        Schema::create('products', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id')->default(9); $t->string('added_by')->default('admin');
            $t->integer('category_id')->default(1); $t->boolean('in_storehouse')->default(1);
            $t->integer('original_id')->nullable(); $t->boolean('published')->default(1);
            $t->string('slug')->default('fixture'); $t->decimal('unit_price', 20, 2)->default(100);
            $t->timestamps();
        });
        Schema::create('product_translations', function (Blueprint $t) {
            $t->increments('id'); $t->integer('product_id'); $t->string('lang'); $t->string('name'); $t->timestamps();
        });
        Schema::create('product_stocks', function (Blueprint $t) {
            $t->increments('id'); $t->integer('product_id'); $t->string('variant')->default('');
            $t->decimal('price', 20, 2)->default(100); $t->string('sku')->default('fixture');
            $t->integer('qty')->default(1000); $t->timestamps();
        });
        Schema::create('product_taxes', function (Blueprint $t) {
            $t->increments('id'); $t->integer('product_id'); $t->integer('tax_id');
            $t->decimal('tax', 20, 2); $t->string('tax_type'); $t->timestamps();
        });
        Schema::create('translations', function (Blueprint $t) {
            $t->increments('id'); $t->string('lang'); $t->string('lang_key'); $t->text('lang_value'); $t->timestamps();
        });
        DB::table('users')->insert(['id' => 1, 'user_type' => 'seller']);
        DB::table('shops')->insert(['user_id' => 1]);
        DB::table('seller_packages')->insert(['id' => 1]);
        DB::table('categories')->insert([
            ['id' => 1, 'parent_id' => 0], ['id' => 2, 'parent_id' => 1], ['id' => 3, 'parent_id' => 0],
        ]);
        $this->actingAs(User::findOrFail(1));
        require_once database_path('migrations/2026_11_17_120000_add_storehouse_bulk_add_translations.php');
        (new \AddStorehouseBulkAddTranslations)->up();
    }

    private function add(array $data)
    {
        return app(ProductStorehouseController::class)->addProduct(Request::create('/', 'POST', $data))->getData(true);
    }

    public function testBulkAddsEveryPageOfTheSelectedCategoryAndChildrenAndSkipsExistingCopies()
    {
        for ($id = 1; $id <= 21; $id++) {
            DB::table('products')->insert(['id' => $id, 'category_id' => $id === 21 ? 2 : 1]);
        }
        DB::table('products')->insert([
            ['id' => 22, 'category_id' => 3],
        ]);
        DB::table('products')->insert(['id' => 23, 'category_id' => 1, 'in_storehouse' => 0]);
        DB::table('products')->insert(['id' => 24, 'category_id' => 1, 'added_by' => 'seller']);
        DB::table('products')->insert(['id' => 25, 'user_id' => 1, 'original_id' => 1,
            'in_storehouse' => 0, 'added_by' => 'seller', 'published' => 0]);
        DB::table('product_translations')->insert(['product_id' => 21, 'lang' => 'tr', 'name' => 'Örnek ürün']);
        DB::table('product_stocks')->insert(['product_id' => 21]);
        DB::table('product_taxes')->insert(['product_id' => 21, 'tax_id' => 1, 'tax' => 5, 'tax_type' => 'percent']);

        // Page, name and brand filters must not limit an entire-category operation.
        $result = $this->add(['all' => 1, 'category' => 'category-1', 'page' => 2, 'keyword' => 'missing', 'brand' => 999]);
        $this->assertSame(1, $result['success']);
        $copies = Product::without('product_translations', 'taxes')->where('user_id', 1)->orderBy('original_id')->get();
        $this->assertSame(range(1, 21), $copies->pluck('original_id')->map(function ($id) { return (int) $id; })->all());
        $childCopy = $copies->last();
        $this->assertSame(110.0, (float) $childCopy->unit_price);
        $this->assertDatabaseHas('product_translations', ['product_id' => $childCopy->id, 'lang' => 'tr', 'name' => 'Örnek ürün']);
        $this->assertDatabaseHas('product_stocks', ['product_id' => $childCopy->id, 'price' => 110, 'qty' => 1000]);
        $this->assertDatabaseHas('product_taxes', ['product_id' => $childCopy->id, 'tax' => 5]);
        $this->assertSame(1, $this->add(['all' => 1, 'category' => 'category-1'])['success']);
        $this->assertSame(21, DB::table('products')->where('user_id', 1)->count());
    }

    /** @dataProvider invalidCategories */
    public function testBulkAddRequiresAValidCategoryEvenWhenProductIdsAreSubmitted($category)
    {
        DB::table('products')->insert(['id' => 1]);
        app()->setLocale('cn');
        $result = $this->add(['all' => 1, 'category' => $category, 'product_ids' => [1]]);
        $this->assertSame(0, $result['success']);
        $this->assertSame('请先选择商品分类', $result['message']);
        $this->assertSame(0, DB::table('products')->where('user_id', 1)->count());
    }

    public function invalidCategories()
    {
        return [[null], [''], ['category-999'], ['category-0'], ['category-1-extra'], ['1'], [['category-1']]];
    }

    public function testManualSelectionStillWorksWithoutACategory()
    {
        DB::table('products')->insert(['id' => 1, 'category_id' => 3]);
        $this->assertSame(1, $this->add(['all' => 0, 'product_ids' => [1]])['success']);
        $this->assertDatabaseHas('products', ['user_id' => 1, 'original_id' => 1]);
    }

    public function testBulkAddStillRespectsVerificationAndProductLimits()
    {
        DB::table('products')->insert(['id' => 1]);
        DB::table('shops')->update(['verification_status' => 0]);
        $this->assertSame(0, $this->add(['all' => 1, 'category' => 'category-1'])['success']);
        DB::table('shops')->update(['verification_status' => 1]);
        auth()->user()->unsetRelation('shop');
        DB::table('seller_packages')->update(['product_upload_limit' => 0]);
        $this->assertSame(0, $this->add(['all' => 1, 'category' => 'category-1'])['success']);
        $this->assertSame(0, DB::table('products')->where('user_id', 1)->count());
    }

    public function testAllSupportedLanguagesHaveASelectionPromptAndMigrationPreservesCustomText()
    {
        $messages = json_decode(file_get_contents(database_path('data/storehouse_bulk_add_translations.json')), true);
        $this->assertCount(13, $messages);
        foreach ($messages as $locale => $labels) {
            $this->assertSame($labels['Please select a category first'], translate('Please select a category first', $locale));
        }
        DB::table('translations')->where('lang', 'cn')->update(['lang_value' => '请选择分类后重试']);
        Cache::put('translations-cn', ['please_select_a_category_first' => 'stale']);
        (new \AddStorehouseBulkAddTranslations)->up();
        $this->assertSame('请选择分类后重试', translate('Please select a category first', 'cn'));
        $this->assertSame(13, DB::table('translations')->count());
    }
}
