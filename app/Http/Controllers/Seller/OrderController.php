<?php

namespace App\Http\Controllers\Seller;

use App\Models\Order;
use App\Models\ProductStock;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Utility\NotificationUtility;
use App\Utility\SmsUtility;
use Illuminate\Http\Request;
use Auth;
use DB;
use Illuminate\Support\Carbon;
use function dd;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource to seller.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $payment_status = null;
        $delivery_status = null;
        $sort_search = null;
        $orders = DB::table('orders')
            ->orderBy('id', 'desc')
            ->where('seller_id', Auth::user()->id)
            ->select('orders.id')
            ->distinct();

        if ($request->payment_status != null) {
            $orders = $orders->where('payment_status', $request->payment_status);
            $payment_status = $request->payment_status;
        }
        if ($request->delivery_status != null) {
            $orders = $orders->where('delivery_status', $request->delivery_status);
            $delivery_status = $request->delivery_status;
        }
        if ($request->has('search')) {
            $sort_search = $request->search;
            $orders = $orders->where('code', 'like', '%' . $sort_search . '%');
        }

        $orders = $orders->paginate(15);

        foreach ($orders as $key => $value) {
            $order = Order::find($value->id);
            $order->viewed = 1;
            $order->save();
        }
        return view('seller.orders.index', compact('orders', 'payment_status', 'delivery_status', 'sort_search'));
    }

    public function show( $id ) {
        $order = Order::findOrFail(decrypt($id));
        $order_shipping_address = json_decode($order->shipping_address);
        $delivery_boys = User::where('city', $order_shipping_address->city)
            ->where('user_type', 'delivery_boy')
            ->get();

        $tpwd = Auth::user()->tpwd;
        $express = '';
        if ( $order->express_info )
        {
            $express = json_decode($order->express_info);
        }

        error_reporting(0);


        $order->viewed = 1;
        $order->save();
        return view('seller.orders.show', compact('order', 'delivery_boys', 'express', 'tpwd'));
    }

    // 钱包余额支付货款
    public function buy_package(Request $request)
    {
        
    }
    public function paymentForStorehouseProductAmount(Request $request)
    {
        if (!$request->filled('order_id')) return response()->json(['success' => 0, 'message' => translate('Something went wrong!')]);

        $orderId = decrypt($request->order_id);
        $order = Order::findOrFail($orderId);
//        dd($order);
        if (!$order || $order->product_storehouse_total <= 0) return response()->json(['success' => 0, 'message' => translate('Something went wrong!')]);
        if ($order->product_storehouse_status == 1) return response()->json(['success' => 0, 'message' => translate('Payment completed')]);

        DB::beginTransaction();
        $shop = $order->shop;
        $user = $shop->user;

        if ($user->balance >= $order->product_storehouse_total) {
            $user->balance -= $order->product_storehouse_total;
            $user->save();

            $shop->admin_to_pay += $order->grand_total;
            $shop->save();


            // 保存订单冻结资金过期时间
            $freezeDays = get_setting('frozen_funds_unfrozen_days', 15);
            $order->freeze_expired_at = Carbon::now()->addDays($freezeDays)->timestamp;

            $order->product_storehouse_status = 1;
            $order->save();

            DB::commit();
            return response()->json(['success' => 1, 'message' => translate('Payment completed')]);
        }
        DB::rollBack();
        return response()->json(['success' => 0, 'message' => translate('Insufficient balance')]);
    }

    // Update Delivery Status
    public function update_delivery_status(Request $request)
    {
        $allowedStatuses = ['pending', 'confirmed', 'picked_up', 'on_the_way', 'delivered', 'cancelled'];
        if (!$request->filled('order_id') || !$request->filled('status') || !in_array($request->status, $allowedStatuses)) {
            return response()->json(['success' => 0, 'message' => translate('Something went wrong')], 422);
        }

        $order = Order::findOrFail($request->order_id);
        if ($order->seller_id != Auth::user()->id) {
            return response()->json(['success' => 0, 'message' => translate('Something went wrong')], 403);
        }

        if (in_array($order->delivery_status, ['delivered', 'cancelled'])) {
            return response()->json(['success' => 0, 'message' => translate('Order status can not be changed')], 422);
        }

        // 云仓订单需先完成提货付款，才能进入发货/签收流程
        $needStorehousePay = $order->product_storehouse_total > 0 && (int)$order->product_storehouse_status !== 1;
        if ($needStorehousePay && in_array($request->status, ['picked_up', 'on_the_way', 'delivered'])) {
            return response()->json(['success' => 0, 'message' => translate('Please complete storehouse payment first')], 422);
        }

        $order->delivery_viewed = '0';
        $order->delivery_status = $request->status;
        $order->save();

        if ($request->status == 'cancelled' && $order->payment_type == 'wallet' && $order->picking_switch == 1) {
            $user = User::where('id', $order->user_id)->first();
            if ($user) {
                $user->balance += $order->grand_total;
                $user->save();
            }
        }

        if ($request->status == 'delivered') {
            product_storehouse_order_free_up($order->id);
        }

        foreach ($order->orderDetails->where('seller_id', Auth::user()->id) as $key => $orderDetail) {
            $orderDetail->delivery_status = $request->status;
            $orderDetail->save();

            if ($request->status == 'cancelled') {
                $variant = $orderDetail->variation;
                if ($orderDetail->variation == null) {
                    $variant = '';
                }

                $product_stock = ProductStock::where('product_id', $orderDetail->product_id)
                    ->where('variant', $variant)
                    ->first();

                if ($product_stock != null) {
                    $product_stock->qty += $orderDetail->quantity;
                    $product_stock->save();
                }
            }
        }

        if (addon_is_activated('otp_system') && SmsTemplate::where('identifier', 'delivery_status_change')->first()->status == 1) {
            try {
                SmsUtility::delivery_status_change(json_decode($order->shipping_address)->phone, $order);
            } catch (\Exception $e) {

            }
        }

        //sends Notifications to user
        NotificationUtility::sendNotification($order, $request->status);
        if (get_setting('google_firebase') == 1 && $order->user && $order->user->device_token != null) {
            $request->device_token = $order->user->device_token;
            $request->title = "Order updated !";
            $status = str_replace("_", "", $order->delivery_status);
            $request->text = " Your order {$order->code} has been {$status}";

            $request->type = "order";
            $request->id = $order->id;
            $request->user_id = $order->user->id;

            NotificationUtility::sendFirebaseNotification($request);
        }

        if (addon_is_activated('delivery_boy')) {
            if (Auth::user()->user_type == 'delivery_boy') {
                $deliveryBoyController = new \App\Http\Controllers\DeliveryBoyController;
                $deliveryBoyController->store_delivery_history($order);
            }
        }

        return response()->json(['success' => 1, 'message' => translate('Order status has been updated')]);
    }

    // Update Payment Status
    public function update_payment_status(Request $request)
    {
        $order = Order::findOrFail($request->order_id);
        $order->payment_status_viewed = '0';
        $order->save();

        foreach ($order->orderDetails->where('seller_id', Auth::user()->id) as $key => $orderDetail) {
            $orderDetail->payment_status = $request->status;
            $orderDetail->save();
        }

        $status = 'paid';
        foreach ($order->orderDetails as $key => $orderDetail) {
            if ($orderDetail->payment_status != 'paid') {
                $status = 'unpaid';
            }
        }
        $order->payment_status = $status;
        $order->save();


        if ($order->payment_status == 'paid' && $order->commission_calculated == 0) {
            calculateCommissionAffilationClubPoint($order);
        }

        //sends Notifications to user
        NotificationUtility::sendNotification($order, $request->status);
        if (get_setting('google_firebase') == 1 && $order->user->device_token != null) {
            $request->device_token = $order->user->device_token;
            $request->title = "Order updated !";
            $status = str_replace("_", "", $order->payment_status);
            $request->text = " Your order {$order->code} has been {$status}";

            $request->type = "order";
            $request->id = $order->id;
            $request->user_id = $order->user->id;

            NotificationUtility::sendFirebaseNotification($request);
        }


        if (addon_is_activated('otp_system') && SmsTemplate::where('identifier', 'payment_status_change')->first()->status == 1) {
            try {
                SmsUtility::payment_status_change(json_decode($order->shipping_address)->phone, $order);
            } catch (\Exception $e) {

            }
        }
        return 1;
    }

}
