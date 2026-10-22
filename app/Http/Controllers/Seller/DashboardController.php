<?php

namespace App\Http\Controllers\Seller;

use App\Models\Order;
use App\Models\Product;
use App\Models\Category;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Session;
use function dd;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
//        dd(url()->previous());
//        dd($request->root().'/users/login');
        if ( $request->root() . '/users/login' == url()->previous() )
        {
            if ( Session::get('conversations-modal') != '0' ) Session::put('conversations-modal', '1');
        }
        $data['products'] = filter_products(Product::where('user_id', Auth::user()->id)->orderBy('num_of_sale', 'desc'))->limit(12)->get();
        // Count only this seller's products in SQL. Never hydrate the platform's
        // product catalogue (and its default translations/taxes) just to count it.
        $categoryCounts = DB::table('products')->where('user_id', Auth::id())
            ->selectRaw('category_id, COUNT(*) as product_count')->groupBy('category_id');
        $data['product_categories'] = Category::query()
            ->joinSub($categoryCounts, 'seller_product_counts', function ($join) {
                $join->on('categories.id', '=', 'seller_product_counts.category_id');
            })
            ->select('categories.*', 'seller_product_counts.product_count')
            ->orderBy('categories.id')->get();
        $sellerOrders = Order::where('seller_id', Auth::user()->id);
        $data['total_orders'] = (clone $sellerOrders)->count();
        $data['total_profit'] = (clone $sellerOrders)
                                ->where('payment_status', 'paid')
                                ->selectRaw('COALESCE(SUM(grand_total - COALESCE(product_storehouse_total, 0)), 0) as total')
                                ->value('total');
        $data['last_7_days_sales'] = Order::where('created_at', '>=', Carbon::now()->subDays(7))
                                ->where('seller_id', '=', Auth::user()->id)
                                ->where('delivery_status', '=', 'delivered')
                                ->select(DB::raw("sum(grand_total) as total, DATE_FORMAT(created_at, '%d %b') as date"))
                                ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d')"))
                                ->get()->pluck('total', 'date');

        return view('seller.dashboard', $data);
    }
}
