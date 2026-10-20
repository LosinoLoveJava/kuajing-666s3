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
    
       public function refuse(Request $request){
        $ibanWithdrawal = SellerWithdrawRequest::findOrFail($request->withdraw_request_id);
        if ((int) $ibanWithdrawal->w_type === 4) {
            app(\App\Services\WithdrawalService::class)->reviewIban($request, $ibanWithdrawal->id, false);
            return back();
        }
        $withdrawRequest = SellerWithdrawRequest::find($request->withdraw_request_id);
        
        if (!$withdrawRequest) {
            flash(translate('Something went wrong'))->error();
            return back();
        }
        $user = User::find($withdrawRequest->user_id);
        if (!$user) {
            flash(translate('Something went wrong'))->error();
            return back();
        }
        if( $withdrawRequest->type ==1 )
        {
            //更新为拒绝
            $withdrawRequest->status = '2';
            $withdrawRequest->viewed = '1';
            $withdrawRequest->remarks = $request->remarks;
            $withdrawRequest->save();
            
            //退款回支付账户
            $user->balance = $user->balance+$withdrawRequest->amount;
            $user->save();
            return redirect()->route('sellers.index');
        }
        else
        {
            
           
                $withdrawRequest->status = '2';
            $withdrawRequest->viewed = '1';
              $withdrawRequest->remarks = $request->remarks;
            $withdrawRequest->save();
            
            
            $shop = Shop::findOrFail( $user->shop->id );
           
            $shop->bzj_money = $shop->bzj_money +$withdrawRequest->amount;
            $shop->save();
            
            
            
            return redirect()->route('sellers.index');
        }
    }
    
    public function refuse234(Request $request){
        $withdrawRequest = SellerWithdrawRequest::find($request->withdraw_request_id);
        
        if (!$withdrawRequest) {
            flash(translate('Something went wrong'))->error();
            return back();
        }
        $user = User::find($withdrawRequest->user_id);
        if (!$user) {
            flash(translate('Something went wrong'))->error();
            return back();
        }
        //更新为拒绝
        $withdrawRequest->status = '2';
        $withdrawRequest->viewed = '1';
        $withdrawRequest->save();
        
        //退款回支付账户
        $user->balance = $user->balance+$withdrawRequest->amount;
        $user->save();
        return redirect()->route('sellers.index');
    }
    
    
    //redirect to payment controllers according to selected payment gateway for seller payment
    public function pay_to_seller(Request $request)
    {
        
/*shop_id: 1
amount: 10
txn_code: 
id: 1
*/


      $shopid = $request->id;
      
      $shop = Shop::findOrFail( $shopid);
      $amount = floatval($request->amount);
    
      if( $amount == 0 )
      {
            flash(translate('Something went wrong'))->error();
            return back();
      }
    
      $user = User::find($shop->user_id);
      
        if (!$user) {
            flash(translate('Something went wrong'))->error();
            return back();
        }
        
     $user->balance = $user->balance + $amount;
     $user->save();
     
     
     
     
     
            $payment = new Payment;
            $payment->seller_id = $user->id;
            $payment->amount = $amount;
            $payment->payment_method = 'Pay From admin';
            $payment->txn_code = date("YmdHis");
            $payment->payment_details = null;
            $payment->save();
            
            
            
            flash(translate('Payment completed'))->success();
            return redirect()->route('sellers.index');
        echo "<PRE>";
        #print_r( $request->);
        exit;
        
        
        
        
        
        $withdrawRequest = SellerWithdrawRequest::find($request->withdraw_request_id);
        if (!$withdrawRequest) {
            flash(translate('Something went wrong'))->error();
            return back();
        }
        $user = User::find($withdrawRequest->user_id);
        if (!$user) {
            flash(translate('Something went wrong'))->error();
            return back();
        }

        $data['shop_id'] = $request->shop_id;
        $data['amount'] = $request->amount;
        $data['payment_method'] = $request->payment_option;
        $data['payment_withdraw'] = $request->payment_withdraw;
        $data['withdraw_request_id'] = $request->withdraw_request_id;

        if ($request->txn_code != null) {
            $data['txn_code'] = $request->txn_code;
        } else {
            $data['txn_code'] = null;
        }

        $request->session()->put('payment_type', 'seller_payment');
        $request->session()->put('payment_data', $data);

        if ($request->payment_option == 'cash') {
            return $this->seller_payment_done($request->session()->get('payment_data'), null, $withdrawRequest, $user);
        } elseif ($request->payment_option == 'bank_payment') {
            return $this->seller_payment_done($request->session()->get('payment_data'), null, $withdrawRequest, $user);
        } else {
            $payment_data = $request->session()->get('payment_data');

//            $shop = Shop::findOrFail($payment_data['shop_id']);
//            $shop->admin_to_pay = $shop->admin_to_pay - $payment_data['amount'];
//            $shop->save();
            $user->balance = $user->balance + $payment_data['amount'];
            $user->save();

            $payment = new Payment;
            $payment->seller_id = $user->id;
            $payment->amount = $payment_data['amount'];
            $payment->payment_method = 'Seller paid to admin';
            $payment->txn_code = $payment_data['txn_code'];
            $payment->payment_details = null;
            $payment->save();

            flash(translate('Payment completed'))->success();
            return redirect()->route('sellers.index');
        }
    }
    
    
    public function pay_to_seller2(Request $request)
    {
        $ibanWithdrawal = SellerWithdrawRequest::findOrFail($request->seller_withdraw_request_id);
        if ((int) $ibanWithdrawal->w_type === 4) {
            app(\App\Services\WithdrawalService::class)->reviewIban($request, $ibanWithdrawal->id, true);
            return back();
        }
        
/*shop_id: 1
amount: 10
txn_code: 
id: 1
*/ 
 
      
        $withdrawRequest = SellerWithdrawRequest::find($request->seller_withdraw_request_id);
       $withdrawRequest->remarks = $request->remarks;
        if (!$withdrawRequest) {
            flash(translate('Something went wrong'))->error();
            return back();
        }
        $user = User::find($withdrawRequest->user_id);
        if (!$user) {
            flash(translate('Something went wrong'))->error();
            return back();
        }

        $data['shop_id'] = $request->shop_id;
        $data['amount'] = $request->amount;
        $data['payment_method'] = $request->payment_option;
        $data['payment_withdraw'] = $request->payment_withdraw;
        $data['withdraw_request_id'] = $request->withdraw_request_id;
       
        if ($request->txn_code != null) {
            $data['txn_code'] = $request->txn_code;
        } else {
            $data['txn_code'] = null;
        }
        
        $request->session()->put('payment_type', 'seller_payment');
        $request->session()->put('payment_data', $data);

        if ($request->payment_option == 'cash') {
            return $this->seller_payment_done($request->session()->get('payment_data'), null, $withdrawRequest, $user);
        } else if ($request->payment_option == 'bank_payment') {
            return $this->seller_payment_done($request->session()->get('payment_data'), null, $withdrawRequest, $user);
        } else if(in_array($request->payment_option, ['usdt_payment', 'iban_payment'], true)) {
                  return $this->seller_payment_done($request->session()->get('payment_data'), null, $withdrawRequest, $user);
        } else {
            $payment_data = $request->session()->get('payment_data');

//            $shop = Shop::findOrFail($payment_data['shop_id']);
//            $shop->admin_to_pay = $shop->admin_to_pay - $payment_data['amount'];
//            $shop->save();
            $user->balance = $user->balance + $payment_data['amount'];
            $user->save();

            $payment = new Payment;
            $payment->seller_id = $user->id;
            $payment->amount = $payment_data['amount'];
            $payment->payment_method = 'Seller paid to admin';
            $payment->txn_code = $payment_data['txn_code'];
            $payment->payment_details = null;
            $payment->t_type = $withdrawRequest->t_type;
            $payment->save();

            flash(translate('Payment completed'))->success();
            return redirect()->route('sellers.withdraw_requests_all.index');
        }
    }
    

    //redirects to this method after successfull seller payment
    public function seller_payment_done($payment_data, $payment_details, $withdrawRequest, $user)
    {
        $withdrawRequest = $withdrawRequest ?: SellerWithdrawRequest::find($payment_data['withdraw_request_id']);
        $user = $user ?: User::find($withdrawRequest->user_id);

        if (!$withdrawRequest || !$user) {
            flash(translate('Something went wrong'))->error();
            return back();
        }
//        $shop = Shop::findOrFail($payment_data['shop_id']);
//        $shop->admin_to_pay = $shop->admin_to_pay - $payment_data['amount'];
//        $shop->save();

        // $user->balance = $user->balance + $payment_data['amount'];
        // $user->save();

        $payment = new Payment;
        $payment->seller_id = $user->id;
        $payment->amount = $payment_data['amount'];
        $payment->payment_method = $payment_data['payment_method'];
        $payment->txn_code = $payment_data['txn_code'];
        $payment->payment_details = $payment_details;
        $payment->t_type = $withdrawRequest->t_type;
        $payment->save();

        if ($payment_data['payment_withdraw'] == 'withdraw_request') {
            $seller_withdraw_request = SellerWithdrawRequest::findOrFail($payment_data['withdraw_request_id']);
            $seller_withdraw_request->status = '1';
            $seller_withdraw_request->viewed = '1';
            $seller_withdraw_request->remarks = $withdrawRequest->remarks;
            $seller_withdraw_request->save();
        }

        Session::forget('payment_data');
        Session::forget('payment_type');

        if ($payment_data['payment_withdraw'] == 'withdraw_request') {
            flash(translate('Payment completed'))->success();
            return redirect()->route('withdraw_requests_all');
        } else {
            flash(translate('Payment completed'))->success();
            return redirect()->route('sellers.index');
        }
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
