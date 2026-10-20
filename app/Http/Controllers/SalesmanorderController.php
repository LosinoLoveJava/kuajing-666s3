<?php

namespace App\Http\Controllers;

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\OTPVerificationController;
use Illuminate\Http\Request;
use App\Http\Controllers\ClubPointController;
use App\Models\Order;
use App\Models\Cart;
use App\Models\Address;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\CommissionHistory;
use App\Models\Color;
use App\Models\OrderDetail;
use App\Models\CouponUsage;
use App\Models\Coupon;
use App\OtpConfiguration;
use App\Models\User;
use App\Models\BusinessSetting;
use App\Models\CombinedOrder;
use App\Models\SmsTemplate;
use Auth;
use Session;
use DB;
use Mail;
use App\Mail\InvoiceEmailManager;
use App\Utility\NotificationUtility;
use CoreComponentRepository;
use App\Utility\SmsUtility;

class SalesmanorderController extends Controller
{

    /**
     * Display a listing of the resource to seller.
     *
     * @return \Illuminate\Http\Response
     */
    public function index( Request $request ) {
        $payment_status = NULL;
        $delivery_status = NULL;
        $sort_search = NULL;
        $pid = Auth::user()->id;
        $userIds = User::where('user_type', 'seller')->where(function ($user) use ($pid) {
            $user->where('pid', $pid);
        })->pluck('id')->toArray();


        $orders = DB::table('orders')
            ->orderBy('id', 'desc')
            ->whereIn('seller_id', $userIds)
            ->select('orders.id')
            ->distinct();

        if ( $request->payment_status != NULL )
        {
            $orders = $orders->where('payment_status', $request->payment_status);
            $payment_status = $request->payment_status;
        }
        if ( $request->delivery_status != NULL )
        {
            $orders = $orders->where('delivery_status', $request->delivery_status);
            $delivery_status = $request->delivery_status;
        }
        if ( $request->has('search') )
        {
            $sort_search = $request->search;
            $orders = $orders->where('code', 'like', '%' . $sort_search . '%');
        }

        $orders = $orders->paginate(15);

        return view('salesman.orders.index', compact('orders', 'payment_status', 'delivery_status', 'sort_search'));
    }

    // All Orders
    public function all_orders( Request $request ) {
        CoreComponentRepository::instantiateShopRepository();

        $date = $request->date;
        $sort_search = NULL;
        $delivery_status = NULL;

        $orders = Order::orderBy('id', 'desc');
        if ( $request->has('search') )
        {
            $sort_search = $request->search;
            $orders = $orders->where('code', 'like', '%' . $sort_search . '%');
        }
        if ( $request->delivery_status != NULL )
        {
            $orders = $orders->where('delivery_status', $request->delivery_status);
            $delivery_status = $request->delivery_status;
        }
        if ( $date != NULL )
        {
            $orders = $orders->where('created_at', '>=', date('Y-m-d', strtotime(explode(" to ", $date)[0])))->where('created_at', '<=', date('Y-m-d', strtotime(explode(" to ", $date)[1])));
        }
        $orders = $orders->paginate(15);
        return view('backend.sales.all_orders.index', compact('orders', 'sort_search', 'delivery_status', 'date'));
    }
    public function all_orders_show( $id ) {
        $order = Order::findOrFail(decrypt($id));

        $express = '';
        if ( $order->express_info )
        {
            $express = json_decode($order->express_info);
        }

        error_reporting(0);


        $order_shipping_address = json_decode($order->shipping_address);
        $delivery_boys = [];
        if ( isset($order_shipping_address->city) )
        {
            $delivery_boys = User::where('city', $order_shipping_address->city)
                ->where('user_type', 'delivery_boy')
                ->get();
        }

        return view('backend.sales.all_orders.show', compact('order', 'delivery_boys', 'express'));
    }
    public function show( $id ) {
        $order = Order::findOrFail(decrypt($id));

        $express = '';
        if ( $order->express_info )
        {
            $express = json_decode($order->express_info);
        }

        error_reporting(0);


        $order_shipping_address = json_decode($order->shipping_address);
        $delivery_boys = [];
        if ( isset($order_shipping_address->city) )
        {
            $delivery_boys = User::where('city', $order_shipping_address->city)
                ->where('user_type', 'delivery_boy')
                ->get();
        }

        return view('salesman.orders.show', compact('order', 'delivery_boys', 'express'));
    }

    // Seller Orders
    public function seller_orders( Request $request ) {
        CoreComponentRepository::instantiateShopRepository();

        $date = $request->date;
        $seller_id = $request->seller_id;
        $payment_status = NULL;
        $delivery_status = NULL;
        $sort_search = NULL;
        $admin_user_id = User::where('user_type', 'admin')->first()->id;
        $orders = Order::orderBy('code', 'desc')
            ->where('orders.seller_id', '!=', $admin_user_id);

        if ( $request->payment_type != NULL )
        {
            $orders = $orders->where('payment_status', $request->payment_type);
            $payment_status = $request->payment_type;
        }
        if ( $request->delivery_status != NULL )
        {
            $orders = $orders->where('delivery_status', $request->delivery_status);
            $delivery_status = $request->delivery_status;
        }
        if ( $request->has('search') )
        {
            $sort_search = $request->search;
            $orders = $orders->where('code', 'like', '%' . $sort_search . '%');
        }
        if ( $date != NULL )
        {
            $orders = $orders->whereDate('created_at', '>=', date('Y-m-d', strtotime(explode(" to ", $date)[0])))->whereDate('created_at', '<=', date('Y-m-d', strtotime(explode(" to ", $date)[1])));
        }
        if ( $seller_id )
        {
            $orders = $orders->where('seller_id', $seller_id);
        }

        $orders = $orders->paginate(15);
        return view('backend.sales.seller_orders.index', compact('orders', 'payment_status', 'delivery_status', 'sort_search', 'admin_user_id', 'seller_id', 'date'));
    }

    public function seller_orders_show( $id ) {
        $order = Order::findOrFail(decrypt($id));
        $order->viewed = 1;
        $order->save();
        return view('backend.sales.seller_orders.show', compact('order'));
    }

    /**
     * Display a single sale to admin.
     *
     * @return \Illuminate\Http\Response
     */


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create() {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store( Request $request ) {
        $carts = Cart::where('user_id', Auth::user()->id)
            ->get();

        if ( $carts->isEmpty() )
        {
            flash(translate('Your cart is empty'))->warning();
            return redirect()->route('home');
        }

        $address = Address::where('id', $carts[0]['address_id'])->first();

        $shippingAddress = [];
        if ( $address != NULL )
        {
            $shippingAddress['name'] = Auth::user()->name;
            $shippingAddress['email'] = Auth::user()->email;
            $shippingAddress['address'] = $address->address;
            $shippingAddress['country'] = $address->country->name;
            $shippingAddress['state'] = $address->state->name;
            $shippingAddress['city'] = $address->city->name;
            $shippingAddress['postal_code'] = $address->postal_code;
            $shippingAddress['phone'] = $address->phone;
            if ( $address->latitude || $address->longitude )
            {
                $shippingAddress['lat_lang'] = $address->latitude . ',' . $address->longitude;
            }
        }

        $combined_order = new CombinedOrder;
        $combined_order->user_id = Auth::user()->id;
        $combined_order->shipping_address = json_encode($shippingAddress);
        $combined_order->save();

        $seller_products = [];
        foreach ( $carts as $cartItem )
        {
            $product_ids = [];
            $product = Product::find($cartItem['product_id']);
            if ( isset($seller_products[$product->user_id]) )
            {
                $product_ids = $seller_products[$product->user_id];
            }
            array_push($product_ids, $cartItem);
            $seller_products[$product->user_id] = $product_ids;
        }

        foreach ( $seller_products as $seller_product )
        {
            $order = new Order;
            $order->combined_order_id = $combined_order->id;
            $order->user_id = Auth::user()->id;
            $order->shipping_address = $combined_order->shipping_address;

            $order->additional_info = $request->additional_info;

            $order->shipping_type = $carts[0]['shipping_type'];
            if ( $carts[0]['shipping_type'] == 'pickup_point' )
            {
                $order->pickup_point_id = $cartItem['pickup_point'];
            }
            $order->payment_type = $request->payment_option;
            $order->delivery_viewed = '0';
            $order->payment_status_viewed = '0';
            $order->code = date('Ymd-His') . rand(10, 99);
            $order->date = strtotime('now');
            $order->save();

            $subtotal = 0;
            $tax = 0;
            $shipping = 0;
            $coupon_discount = 0;

            // 产品仓库的产品货款
            $productStorehouseTotal = 0;

            //Order Details Storing
            foreach ( $seller_product as $cartItem )
            {
                $product = Product::find($cartItem['product_id']);

                // 计算产品仓库的产品货款
                $originalProduct = NULL;
                if ( $product->original_id )
                {
                    $originalProduct = Product::query()->find($product->original_id);
                    if ( $originalProduct )
                    {
                        $productStorehouseTotal += cart_product_price($cartItem, $originalProduct, false, false) * $cartItem['quantity'];
                    }
                }


                $subtotal += cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                $tax += cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
                $coupon_discount += $cartItem['discount'];

                $product_variation = $cartItem['variation'];

                $product_stock = $product->stocks->where('variant', $product_variation)->first();
                if ( $product->digital != 1 && $cartItem['quantity'] > $product_stock->qty )
                {
                    flash(translate('The requested quantity is not available for ') . $product->getTranslation('name'))->warning();
                    $order->delete();
                    return redirect()->route('cart')->send();
                }
                else if ( $product->digital != 1 )
                {
                    $product_stock->qty -= $cartItem['quantity'];
                    $product_stock->save();
                }

                $order_detail = new OrderDetail;
                $order_detail->order_id = $order->id;
                $order_detail->seller_id = $product->user_id;
                $order_detail->product_id = $product->id;
                $order_detail->is_storehouse_product = $product->original_id ? 1 : 0; // 是否产品仓库产品
                $order_detail->original_product_id = $product->original_id ?: NULL; // 原产品仓库产品ID
                $order_detail->original_product_price = $originalProduct ? $originalProduct->unit_price : NULL; // 原产品仓库产品价格(进货价)
                $order_detail->variation = $product_variation;
                $order_detail->price = cart_product_price($cartItem, $product, false, false) * $cartItem['quantity'];
                $order_detail->tax = cart_product_tax($cartItem, $product, false) * $cartItem['quantity'];
                $order_detail->shipping_type = $cartItem['shipping_type'];
                $order_detail->product_referral_code = $cartItem['product_referral_code'];
                $order_detail->shipping_cost = $cartItem['shipping_cost'];

                $shipping += $order_detail->shipping_cost;
                //End of storing shipping cost

                $order_detail->quantity = $cartItem['quantity'];
                $order_detail->save();

                $product->num_of_sale += $cartItem['quantity'];
                $product->save();

                $order->seller_id = $product->user_id;

                if ( $product->added_by == 'seller' && $product->user->seller != NULL )
                {
                    $seller = $product->user->seller;
                    $seller->num_of_sale += $cartItem['quantity'];
                    $seller->save();
                }

                if ( addon_is_activated('affiliate_system') )
                {
                    if ( $order_detail->product_referral_code )
                    {
                        $referred_by_user = User::where('referral_code', $order_detail->product_referral_code)->first();

                        $affiliateController = new AffiliateController;
                        $affiliateController->processAffiliateStats($referred_by_user->id, 0, $order_detail->quantity, 0, 0);
                    }
                }
            }

            $order->grand_total = $subtotal + $tax + $shipping;
            $order->product_storehouse_total = $productStorehouseTotal;

            if ( $seller_product[0]->coupon_code != NULL )
            {
                // if (Session::has('club_point')) {
                //     $order->club_point = Session::get('club_point');
                // }
                $order->coupon_discount = $coupon_discount;
                $order->grand_total -= $coupon_discount;

                $coupon_usage = new CouponUsage;
                $coupon_usage->user_id = Auth::user()->id;
                $coupon_usage->coupon_id = Coupon::where('code', $seller_product[0]->coupon_code)->first()->id;
                $coupon_usage->save();
            }

            $combined_order->grand_total += $order->grand_total;

            $order->save();
        }

        $combined_order->save();

        $request->session()->put('combined_order_id', $combined_order->id);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */


    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function edit( $id ) {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int                      $id
     *
     * @return \Illuminate\Http\Response
     */
    public function update( Request $request, $id ) {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy( $id ) {
        $order = Order::findOrFail($id);
        if ( $order != NULL )
        {
            foreach ( $order->orderDetails as $key => $orderDetail )
            {
                try
                {

                    $product_stock = ProductStock::where('product_id', $orderDetail->product_id)->where('variant', $orderDetail->variation)->first();
                    if ( $product_stock != NULL )
                    {
                        $product_stock->qty += $orderDetail->quantity;
                        $product_stock->save();
                    }

                }
                catch ( \Exception $e )
                {

                }

                $orderDetail->delete();
            }
            $order->delete();
            flash(translate('Order has been deleted successfully'))->success();
        }
        else
        {
            flash(translate('Something went wrong'))->error();
        }
        return back();
    }

    public function order_details( Request $request ) {
        $order = Order::findOrFail($request->order_id);
        $order->save();
        return view('seller.order_details_seller', compact('order'));
    }
}
