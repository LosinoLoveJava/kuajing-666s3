<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerOrderDeliveryPermissionTest extends TestCase
{
    use \Tests\Concerns\WalletDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWalletDatabase();
        $this->withoutMiddleware();
        Notification::fake();
        foreach ([1=>'admin', 2=>'seller', 3=>'customer', 4=>'staff', 5=>'salesman', 6=>'delivery_boy', 7=>'seller'] as $id=>$role) {
            $user = new User;
            $user->forceFill(['id'=>$id, 'name'=>$role, 'user_type'=>$role, 'balance'=>'100.00'])->save();
        }
        Schema::create('addons', function (Blueprint $t) { $t->increments('id'); });
        Schema::create('notifications', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('notifiable_type'); $t->integer('notifiable_id');
            $t->text('data'); $t->timestamp('read_at')->nullable(); $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->increments('id'); $t->integer('seller_id'); $t->integer('user_id'); $t->string('code');
            $t->string('delivery_status')->default('pending'); $t->integer('delivery_viewed')->default(1);
            $t->string('payment_status')->default('paid'); $t->string('payment_type')->default('wallet');
            $t->decimal('grand_total',20,2)->default(50); $t->integer('picking_switch')->default(1);
            $t->decimal('product_storehouse_total',20,2)->default(0); $t->integer('product_storehouse_status')->default(0);
            $t->text('shipping_address')->nullable(); $t->timestamps();
        });
        Schema::create('order_details', function (Blueprint $t) {
            $t->increments('id'); $t->integer('order_id'); $t->integer('seller_id'); $t->integer('product_id');
            $t->string('delivery_status')->default('pending'); $t->string('payment_status')->default('paid');
            $t->string('variation')->default(''); $t->integer('quantity')->default(1);
            $t->decimal('price',20,2)->default(50); $t->decimal('tax',20,2)->default(0);
            $t->decimal('shipping_cost',20,2)->default(0); $t->timestamps();
        });
        Schema::create('product_stocks', function (Blueprint $t) {
            $t->increments('id'); $t->integer('product_id'); $t->string('variant'); $t->integer('qty'); $t->timestamps();
        });
        foreach ([1=>2, 2=>7] as $id=>$seller) {
            DB::table('orders')->insert(['id'=>$id, 'seller_id'=>$seller, 'user_id'=>3, 'code'=>'ORDER-'.$id,
                'shipping_address'=>json_encode(['name'=>'Test buyer', 'email'=>'buyer@example.test', 'phone'=>'123456789',
                    'address'=>'Test street', 'city'=>'Test city', 'country'=>'AE', 'postal_code'=>'00000'])]);
            DB::table('order_details')->insert(['order_id'=>$id, 'seller_id'=>$seller, 'product_id'=>$id]);
            DB::table('product_stocks')->insert(['product_id'=>$id, 'variant'=>'', 'qty'=>5]);
        }
        app()->setLocale('en');
    }

    private function snapshot()
    {
        $state = [];
        foreach (['orders','order_details','users','product_stocks','wallets','payments'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        return $state;
    }

    public function statuses()
    {
        return array_map(function ($status) { return [$status]; },
            ['pending','confirmed','picked_up','on_the_way','delivered','cancelled']);
    }

    /** @dataProvider statuses */
    public function testSellerCannotChangeAnyDeliveryStatusThroughAnyEndpoint($status)
    {
        $this->actingAs(User::findOrFail(2));
        $before = $this->snapshot();
        foreach ([1, 2, 99999] as $orderId) {
            foreach (['/seller/orders/update_delivery_status', '/admin/orders/update_delivery_status',
                '/orders/update_delivery_status?portal=seller', '/api/v2/delivery-boy/change-delivery-status'] as $path) {
                $this->postJson($path, ['order_id'=>$orderId, 'status'=>$status, 'user_type'=>'admin', 'delivery_boy_id'=>6])
                    ->assertForbidden()->assertJsonPath('message', __('seller_orders.delivery_status_admin_only'));
                $this->assertSame($before, $this->snapshot());
            }
        }
        Notification::assertNothingSent();
    }

    public function adminRoles() { return [[1], [4]]; }

    /** @dataProvider adminRoles */
    public function testAdminPanelStillUpdatesOrderAndAllDetails($adminId)
    {
        $this->actingAs(User::findOrFail($adminId));
        DB::table('order_details')->insert(['order_id'=>1, 'seller_id'=>7, 'product_id'=>2]);
        $this->postJson('/admin/orders/update_delivery_status', ['order_id'=>1, 'status'=>'confirmed'])->assertOk();
        $this->assertDatabaseHas('orders', ['id'=>1, 'delivery_status'=>'confirmed', 'delivery_viewed'=>0]);
        $this->assertSame(['confirmed'], DB::table('order_details')->where('order_id',1)->pluck('delivery_status')->unique()->values()->all());
        $this->assertDatabaseHas('orders', ['id'=>2, 'delivery_status'=>'pending', 'delivery_viewed'=>1]);
        $this->assertDatabaseHas('users', ['id'=>3, 'balance'=>100]);
        $this->assertDatabaseHas('product_stocks', ['product_id'=>1, 'qty'=>5]);
    }

    public function nonAdminRoles() { return [[3], [5], [6]]; }

    /** @dataProvider nonAdminRoles */
    public function testNonAdminRolesCannotUseMainOrLegacyStatusHandler($id)
    {
        $this->actingAs(User::findOrFail($id));
        $before = $this->snapshot();
        foreach (['/admin/orders/update_delivery_status', '/orders/update_delivery_status'] as $path) {
            $this->postJson($path, ['order_id'=>1, 'status'=>'cancelled'])->assertForbidden();
        }
        $this->assertSame($before, $this->snapshot());
        Notification::assertNothingSent();
    }

    public function testApiStatusUpdatesAreDisabledEvenWithAnAdminIdentity()
    {
        $this->actingAs(User::findOrFail(1));
        $before = $this->snapshot();
        $this->postJson('/api/v2/delivery-boy/change-delivery-status', ['order_id'=>1, 'status'=>'delivered'])
            ->assertForbidden()->assertJsonPath('result', false);
        $this->assertSame($before, $this->snapshot());
    }

    public function displayModes() { return [[0,'pending'], [0,'on_the_way'], [0,'delivered'], [0,'cancelled'], [1,'confirmed']]; }

    /** @dataProvider displayModes */
    public function testSellerDetailsAlwaysDisplayReadOnlyDeliveryStatus($managedByAdmin, $status)
    {
        $this->actingAs(User::findOrFail(2));
        DB::table('business_settings')->insert(['type'=>'product_manage_by_admin', 'value'=>$managedByAdmin]);
        session(['currency_exchange_rate'=>1, 'currency_symbol'=>'$']);
        app('view')->getFinder()->prependLocation(base_path('tests/Fixtures/views'));
        app('view')->getFinder()->flush();
        $order = Order::with('orderDetails')->findOrFail(1);
        $order->delivery_status = $status;
        foreach ($order->orderDetails as $detail) $detail->setRelation('product', null);
        $html = view('seller.orders.show', ['order'=>$order, 'delivery_boys'=>collect(),
            'express'=>(object)['express_info'=>[]], 'tpwd'=>''])->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $statusFields = $xpath->query('//input[@id="delivery_status"]');
        $this->assertSame(1, $statusFields->length);
        $this->assertTrue($statusFields->item(0)->hasAttribute('readonly'));
        $this->assertSame(translate(ucfirst(str_replace('_',' ', $status))), $statusFields->item(0)->getAttribute('value'));
        $this->assertSame(0, $xpath->query('//select[@id="update_delivery_status"]')->length);
        $this->assertStringNotContainsString('seller/orders/update_delivery_status', $html);
        $this->assertStringContainsString(__('seller_orders.delivery_status_admin_only'), $html);
    }
}
