<?php

namespace App\Http\Controllers;

use App\Models\CommissionHistory;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\SellerWithdrawRequest;
use App\Models\Seller;
use App\Models\Payment;
use App\Models\Shop;
use Illuminate\Support\Carbon;
use Session;

class CommissionController extends Controller
{
    
       public function refuse(Request $request)
    {
        app(\App\Services\WithdrawalService::class)->review($request, $request->withdraw_request_id, false);
        flash(translate('Request has been updated successfully'))->success();
        return back();
    }
    
    public function refuse234(Request $request)
    {
        app(\App\Services\WithdrawalService::class)->review($request, $request->withdraw_request_id, false);
        flash(translate('Request has been updated successfully'))->success();
        return back();
    }
    
    
    //redirect to payment controllers according to selected payment gateway for seller payment
    public function pay_to_seller(Request $request)
    {
        abort_unless($request->user() && in_array($request->user()->user_type, ['admin', 'staff'], true), 403);
        $shop = Shop::findOrFail($request->id);
        $request->merge(['user_id' => $shop->user_id]);
        app(\App\Services\WalletService::class)->directCredit($request);
        flash(translate('Recharge has been done'))->success();
        return back();
    }
    
    
    public function pay_to_seller2(Request $request)
    {
        app(\App\Services\WithdrawalService::class)->review($request, $request->seller_withdraw_request_id, true);
        flash(translate('Payment completed'))->success();
        return back();
    }
    

    //redirects to this method after successfull seller payment
    public function seller_payment_done($payment_data, $payment_details, $withdrawRequest = null, $user = null)
    {
        app(\App\Services\WithdrawalService::class)->review(request(), $withdrawRequest ? $withdrawRequest->id : ($payment_data['withdraw_request_id'] ?? null), true);
        Session::forget('payment_data');
        Session::forget('payment_type');
        return back();
    }

    //calculate seller commission after payment
    public function calculateCommission($order)
    {
        if ($order->payment_type == 'cash_on_delivery') {
            foreach ($order->orderDetails as $orderDetail) {
                $orderDetail->payment_status = 'paid';
                $orderDetail->save();
                $commission_percentage = 0;

                if (get_setting('vendor_commission_activation')) {
                    if (get_setting('category_wise_commission')) {
                        $commission_percentage = $orderDetail->product->category->commision_rate;
                    } else if ($orderDetail->product->user->user_type == 'seller') {
                        $commission_percentage = get_setting('vendor_commission');
                    }
                }
                if ($orderDetail->product->user->user_type == 'seller') {
                    $shop = $orderDetail->product->user->shop;
                    $admin_commission = ($orderDetail->price * $commission_percentage) / 100;

                    if (get_setting('product_manage_by_admin') == 1) {
                        $shop_earning = ($orderDetail->tax + $orderDetail->price) - $admin_commission;
                        $shop->admin_to_pay += $shop_earning;
                    } else {
                        $shop_earning = ($orderDetail->tax + $orderDetail->shipping_cost + $orderDetail->price) - $admin_commission;
                        $shop->admin_to_pay -= $admin_commission;
                    }

                    $shop->save();

                    $commission_history = new CommissionHistory;
                    $commission_history->order_id = $order->id;
                    $commission_history->order_detail_id = $orderDetail->id;
                    $commission_history->seller_id = $orderDetail->seller_id;
                    $commission_history->admin_commission = $admin_commission;
                    $commission_history->seller_earning = $shop_earning;

                    $commission_history->save();
                }
            }
        } else {
            foreach ($order->orderDetails as $orderDetail) {
                $orderDetail->payment_status = 'paid';
                $orderDetail->save();
                $commission_percentage = 0;

                if (get_setting('vendor_commission_activation')) {
                    if (get_setting('category_wise_commission')) {
                        $commission_percentage = $orderDetail->product->category->commision_rate;
                    } else if ($orderDetail->product->user->user_type == 'seller') {
                        $commission_percentage = get_setting('vendor_commission');
                    }
                }

                if ($orderDetail->product->user->user_type == 'seller') {
                    $shop = $orderDetail->product->user->shop;
                    $admin_commission = ($orderDetail->price * $commission_percentage) / 100;

                    $shop_earning_copy = 0;
                    if (!$order->product_storehouse_total) {
                        if (get_setting('product_manage_by_admin') == 1) {
                            $shop_earning = ($orderDetail->tax + $orderDetail->price) - $admin_commission;
                            $shop->admin_to_pay += $shop_earning;
                        } else {
                            $shop_earning = ($orderDetail->tax + $orderDetail->shipping_cost + $orderDetail->price) - $admin_commission;
                            $shop->admin_to_pay += $shop_earning;
                        }
                        $shop->save();
                        $shop_earning_copy = $shop_earning;
                    }

                    $commission_history = new CommissionHistory;
                    $commission_history->order_id = $order->id;
                    $commission_history->order_detail_id = $orderDetail->id;
                    $commission_history->seller_id = $orderDetail->seller_id;
                    $commission_history->admin_commission = $admin_commission;
                    $commission_history->seller_earning = $shop_earning_copy;

                    $commission_history->save();
                }
            }
//            // 保存订单冻结资金过期时间
//            if ($order->product_storehouse_total > 0) {
//                $freezeDays = get_setting('frozen_funds_unfrozen_days', 15);
//                $order->freeze_expired_at = Carbon::now()->addDays($freezeDays)->timestamp;
//                $order->save();
//            }

//            $shop = $order->shop;
//            $shop->freeze_funds += $order->product_storehouse_total; // 冻结资金
//            $shop->save();

            if ($order->shop != null) {
                $order->shop->admin_to_pay -= $order->coupon_discount;
                $order->shop->save();
            }
        }
    }
}
