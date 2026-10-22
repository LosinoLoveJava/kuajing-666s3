<?php

namespace Tests\Feature;

use App\Http\Controllers\Seller\PaymentController;
use App\Http\Controllers\Seller\SellerWithdrawRequestController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SellerWalletDisplayTest extends TestCase
{
    use \Tests\Concerns\WalletDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createWalletDatabase();
        Schema::table('shops', function (Blueprint $t) { $t->decimal('admin_to_pay', 20, 2)->default(0); });
        Schema::create('orders', function (Blueprint $t) {
            $t->increments('id'); $t->integer('seller_id'); $t->string('code');
            $t->integer('freeze_expired_at')->nullable(); $t->boolean('product_storehouse_status')->default(0);
            $t->decimal('grand_total', 20, 2)->default(30); $t->decimal('product_storehouse_total', 20, 2)->default(20);
            $t->string('payment_status')->default('paid'); $t->timestamps();
        });
        Schema::create('addons', function (Blueprint $t) { $t->increments('id'); });
        Schema::create('notifications', function (Blueprint $t) {
            $t->string('id')->primary(); $t->string('notifiable_type'); $t->integer('notifiable_id');
            $t->text('data'); $t->timestamp('read_at')->nullable(); $t->timestamps();
        });
        DB::table('users')->insert([
            ['id'=>1, 'name'=>'Seller A', 'user_type'=>'seller'],
            ['id'=>2, 'name'=>'Seller B', 'user_type'=>'seller'],
        ]);
        DB::table('shops')->insert(['user_id'=>1]);
        $this->actingAs(User::findOrFail(1));
        foreach (['decimal_separator'=>1, 'no_of_decimals'=>2, 'symbol_format'=>1] as $key=>$value) {
            DB::table('business_settings')->insert(['type'=>$key, 'value'=>$value]);
        }
        session(['currency_exchange_rate'=>1, 'currency_symbol'=>'$']);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        app('view')->getFinder()->prependLocation(base_path('tests/Fixtures/views'));
        app('view')->getFinder()->flush();
    }

    private function request(array $query = [])
    {
        $request = Request::create('https://admin.example.test/seller/money-withdraw-requests', 'GET', $query);
        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);
    }

    private function records($owner, $count)
    {
        for ($i=0; $i<$count; ++$i) {
            $date = '2026-10-23 12:00:00';
            DB::table('wallets')->insert(['user_id'=>$owner, 'amount'=>12.34, 'approval'=>$i % 3,
                'payment_method'=>'Bank', 'created_at'=>$date]);
            DB::table('seller_withdraw_requests')->insert(['user_id'=>$owner, 'amount'=>10,
                'status'=>$i % 3, 'w_type'=>1, 'viewed'=>1, 'message'=>'Withdrawal '.$owner,
                'remarks'=>'', 'created_at'=>$date]);
            DB::table('orders')->insert(['seller_id'=>$owner, 'code'=>'ORDER-'.$owner.'-'.$i, 'created_at'=>$date]);
            DB::table('payments')->insert(['seller_id'=>$owner, 'amount'=>10, 'payment_method'=>'bank_payment',
                'payment_details'=>'Payment '.$owner, 'created_at'=>$date]);
        }
    }

    public function testAllWalletSectionsOnlyContainTheCurrentSellersRecordsAndRetainStableOrder()
    {
        $this->records(1, 12); $this->records(2, 2);
        $this->request(['user_id'=>2, 'seller_id'=>2]);
        $data = app(SellerWithdrawRequestController::class)->index()->getData();
        foreach (['seller_withdraw_requests'=>'user_id', 'freezeOrders'=>'seller_id',
            'rechargeList'=>'user_id', 'paymentList'=>'seller_id'] as $key=>$owner) {
            $this->assertSame(12, $data[$key]->total());
            $this->assertEquals([1], $data[$key]->pluck($owner)->unique()->values()->all());
            $this->assertEquals(range(12, $key === 'paymentList' ? 1 : 4), $data[$key]->pluck('id')->all());
        }
        $this->assertEquals(100, $data['balance']);
        $this->assertEquals(100, User::findOrFail(1)->balance);
    }

    public function testFourPaginatorsRemainIndependentAndPreserveTheOtherSectionsPages()
    {
        $this->records(1, 20);
        $pages = ['page'=>2, 'opage'=>1, 'rpage'=>2, 'ppage'=>2];
        $this->request($pages);
        $view = app(SellerWithdrawRequestController::class)->index();
        $data = $view->getData();
        foreach (['seller_withdraw_requests'=>['page',2,9], 'freezeOrders'=>['opage',1,9],
            'rechargeList'=>['rpage',2,9], 'paymentList'=>['ppage',2,5]] as $key=>$expected) {
            $page = $data[$key];
            $this->assertSame($expected[0], $page->getPageName());
            $this->assertSame($expected[1], $page->currentPage());
            $this->assertSame($expected[2], $page->count());
            parse_str(parse_url($page->url(1), PHP_URL_QUERY), $query);
            foreach ($pages as $name=>$number) {
                $this->assertEquals($name === $page->getPageName() ? 1 : $number, $query[$name]);
            }
        }
        $html = $view->render();
        $this->assertStringContainsString('<td>10</td>', $html);
        $this->assertStringContainsString('<td>16</td>', $html);
    }

    public function testEmptyWalletShowsAnExplicitMessageInEverySection()
    {
        $this->request();
        $html = app(SellerWithdrawRequestController::class)->index()->render();
        $this->assertSame(5, substr_count($html, 'Nothing found'));
        $this->assertSame(5, substr_count($html, 'class="aiz-table-scroll"'));
        $this->assertStringContainsString('seller-wallet-page', $html);
    }

    public function testExistingWalletRecordsAndEveryApprovalStateAreRendered()
    {
        $this->records(1, 3); $this->records(2, 1);
        $this->request();
        $html = app(SellerWithdrawRequestController::class)->index()->render();
        $this->assertStringContainsString('ORDER-1-0', $html);
        $this->assertStringContainsString('Payment 1', $html);
        $this->assertStringContainsString('Pending', $html);
        $this->assertStringContainsString('Approved', $html);
        $this->assertStringContainsString('Rejected', $html);
        $this->assertStringNotContainsString('Payment 2', $html);
        $this->assertStringNotContainsString('ORDER-2-', $html);
    }

    public function testPaymentHistoryShowsTheNewestOwnPaymentsWithCorrectPageNumbers()
    {
        $this->records(1, 12); $this->records(2, 1);
        $this->request(['page'=>2]);
        $view = app(PaymentController::class)->index();
        $this->assertSame(12, $view->getData()['payments']->total());
        $this->assertEquals([3,2,1], $view->getData()['payments']->pluck('id')->all());
        $html = $view->render();
        $this->assertMatchesRegularExpression('/<td>\s*10\s*<\/td>/', $html);
        $this->assertStringNotContainsString('Nothing found', $html);
    }

    public function testEmptyPaymentHistoryDoesNotRenderABlankCard()
    {
        $this->request();
        $html = app(PaymentController::class)->index()->render();
        $this->assertStringContainsString('Nothing found', $html);
        $this->assertStringContainsString('<table', $html);
    }

    public function testTransactionHistoryIncludesWithdrawalsAndPaymentsWithoutAnyRecharge()
    {
        $this->records(1, 2); $this->records(2, 1);
        DB::table('wallets')->where('user_id', 1)->delete();
        DB::table('payments')->insert(['seller_id'=>1, 'amount'=>100, 'payment_method'=>'pay_from_admin',
            'created_at'=>'2026-10-23 13:00:00']);
        DB::table('payments')->insert(['seller_id'=>1, 'amount'=>999, 'payment_method'=>'bank_payment', 't_type'=>2]);
        $this->request(['user_id'=>2, 'seller_id'=>2]);
        $view = app(SellerWithdrawRequestController::class)->index();
        $data = $view->getData();
        $this->assertSame(0, $data['rechargeList']->total());
        $this->assertSame(5, $data['walletHistory']->total());
        $this->assertSame(['payment'=>3, 'withdrawal'=>2], $data['walletHistory']->getCollection()->countBy('source')->all());
        $this->assertEquals(100, $data['walletHistory']->first()->amount);
        $html = $view->render();
        $this->assertStringContainsString('Wallet Transaction History', $html);
        $this->assertSame(5, substr_count($html, 'data-history-source='));
        $this->assertEquals(100, User::findOrFail(1)->balance);
    }

    public function testTransactionHistoryHasStableIndependentPaginationAndShowsAllSourcesAndStatuses()
    {
        $this->records(1, 20); $this->records(2, 2);
        $this->request(['page'=>2, 'opage'=>2, 'rpage'=>2, 'ppage'=>2, 'hpage'=>2]);
        $data = app(SellerWithdrawRequestController::class)->index()->getData();
        $history = $data['walletHistory'];
        $this->assertSame(60, $history->total());
        $this->assertSame(2, $history->currentPage());
        $this->assertSame(15, $history->count());
        $this->assertSame(['payment'=>5, 'recharge'=>10], $history->getCollection()->countBy('source')->all());
        $this->assertEquals([5,4,3,2,1,20,19,18,17,16,15,14,13,12,11], $history->pluck('id')->all());
        parse_str(parse_url($history->url(1), PHP_URL_QUERY), $query);
        $this->assertEquals(['page'=>2, 'opage'=>2, 'rpage'=>2, 'ppage'=>2, 'hpage'=>1], $query);
        $this->assertStringEndsWith('#wallet-history', $history->url(1));
        $this->request(['hpage'=>3]);
        $data = app(SellerWithdrawRequestController::class)->index()->getData();
        $this->assertEquals([2], $data['walletHistory']->pluck('status')->filter(function ($status) { return (int) $status === 2; })->unique()->values()->all());
        $this->request(['hpage'=>3]);
        $html = app(SellerWithdrawRequestController::class)->index()->render();
        $this->assertStringContainsString('Approved', $html);
        $this->assertStringContainsString('Rejected', $html);
        $this->assertStringContainsString('Pending', $html);
        app()->setLocale('cn');
        $this->assertSame('钱包收支明细', __('seller_wallet.history'));
        $this->assertSame('钱包充值记录', __('seller_wallet.recharge_history'));
    }
}
