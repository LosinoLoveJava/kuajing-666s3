<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerProductDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite', 'database.connections.sqlite.database'=>':memory:',
            'cache.default'=>'array', 'session.driver'=>'array', 'portals.domain'=>'example.test']);
        DB::purge('sqlite');
        app('view')->getFinder()->prependLocation(base_path('tests/Fixtures/views'));
        app('view')->getFinder()->flush();
        $this->withoutMiddleware([\App\Http\Middleware\Language::class,
            \App\Http\Middleware\HttpsProtocol::class, \App\Http\Middleware\CheckForMaintenanceMode::class]);
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('user_type');
            $t->boolean('banned')->default(false); $t->timestamp('email_verified_at')->nullable();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->string('name');
        });
        foreach (['product_translations','product_stocks','product_taxes','carts'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->increments('id'); $t->integer('product_id'); });
        }
        Schema::create('translations', function (Blueprint $t) {
            $t->increments('id'); $t->string('lang'); $t->string('lang_key');
            $t->text('lang_value'); $t->timestamps();
        });
        DB::table('users')->insert([
            ['id'=>1,'name'=>'Seller','user_type'=>'seller','email_verified_at'=>now()],
            ['id'=>2,'name'=>'Other seller','user_type'=>'seller','email_verified_at'=>now()],
            ['id'=>3,'name'=>'Customer','user_type'=>'customer','email_verified_at'=>now()],
            ['id'=>4,'name'=>'Admin','user_type'=>'admin','email_verified_at'=>now()],
        ]);
        foreach ([10=>1,20=>2] as $id=>$owner) {
            DB::table('products')->insert(['id'=>$id,'user_id'=>$owner,'name'=>'Fixture product']);
            foreach (['product_translations','product_stocks','product_taxes','carts'] as $table) {
                DB::table($table)->insert(['product_id'=>$id]);
            }
        }
    }

    /** @dataProvider returnLocations */
    public function testDeletionAlwaysReturnsToProductListAndOnlyRemovesItsOwnRelations($host, $referer)
    {
        $this->actingAs(User::findOrFail(1));
        Artisan::shouldReceive('call')->once()->with('view:clear')->andReturn(0);
        Artisan::shouldReceive('call')->once()->with('cache:clear')->andReturn(0);
        $headers = $referer === null ? [] : ['Referer'=>$referer];
        $this->get($host.'/seller/products/destroy/10', $headers)->assertRedirect($host.'/seller/products');
        $this->assertDatabaseMissing('products',['id'=>10]);
        $this->assertDatabaseHas('products',['id'=>20,'user_id'=>2]);
        foreach (['product_translations','product_stocks','product_taxes','carts'] as $table) {
            $this->assertDatabaseMissing($table,['product_id'=>10]);
            $this->assertDatabaseHas($table,['product_id'=>20]);
        }
        $this->get($host.'/seller/products/destroy/10', $headers)->assertRedirect($host.'/seller/products');
    }

    public function returnLocations()
    {
        return [
            ['https://seller.example.test','https://seller.example.test/seller/products?page=2'],
            ['https://seller.example.test','https://seller.example.test/seller/products/destroy/10'],
            ['https://seller.example.test','https://seller.example.test/seller/product/10/edit'],
            ['https://admin.example.test','https://admin.example.test/seller/products'],
            ['https://seller.example.test',null],
        ];
    }

    public function testAnAlreadyDeletedProductReturnsToListWithout404OrOtherChanges()
    {
        $this->actingAs(User::findOrFail(1));
        Artisan::shouldReceive('call')->never();
        $this->get('https://seller.example.test/seller/products/destroy/999')->assertRedirect('https://seller.example.test/seller/products');
        $this->get('https://seller.example.test/seller/products/destroy/999')->assertRedirect('https://seller.example.test/seller/products');
        $this->assertSame(2,DB::table('products')->count());
        $this->assertSame(2,DB::table('product_stocks')->count());
    }

    public function testAnotherSellersProductIsPreservedAndReturnsToList()
    {
        $this->actingAs(User::findOrFail(1));
        Artisan::shouldReceive('call')->never();
        $this->get('https://seller.example.test/seller/products/destroy/20')->assertRedirect('https://seller.example.test/seller/products');
        $this->assertDatabaseHas('products',['id'=>20,'user_id'=>2]);
        $this->assertDatabaseHas('product_stocks',['product_id'=>20]);
    }

    public function testExpiredLoginRedirectsToLoginAndResumesAtListInsteadOfReplayingDeletion()
    {
        $this->get('https://admin.example.test/seller/products/destroy/10')
            ->assertRedirect('/login?portal=seller')->assertSessionHas('link','https://admin.example.test/seller/products');
        $this->assertDatabaseHas('products',['id'=>10]);
    }

    public function testGuestProductListAlsoRedirectsToSellerLogin()
    {
        $this->get('https://seller.example.test/seller/products')->assertRedirect('/login?portal=seller');
    }

    /** @dataProvider otherRoles */
    public function testOtherRolesCannotDeleteSellerProducts($id)
    {
        $this->actingAs(User::findOrFail($id));
        $this->get('https://seller.example.test/seller/products/destroy/10')->assertNotFound();
        $this->assertDatabaseHas('products',['id'=>10]);
    }

    public function otherRoles() { return [[3],[4]]; }

    public function testBannedSellerCannotDeleteProducts()
    {
        DB::table('users')->where('id',1)->update(['banned'=>1]);
        $this->actingAs(User::findOrFail(1));
        $this->get('https://seller.example.test/seller/products/destroy/10')->assertNotFound();
        $this->assertDatabaseHas('products',['id'=>10]);
    }
}
