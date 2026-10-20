<?php

namespace App\Http\Controllers\Seller;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Wallet;
use Illuminate\Http\Request;
use App\Models\SellerWithdrawRequest;
use App\Models\User;
use App\Models\Shop;
use Auth;

class SellerWithdrawRequestController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = Auth::user();
        $balance = $user->balance;
        $userId = $user->id;
        $seller_withdraw_requests = SellerWithdrawRequest::where('user_id', $userId)->latest()->paginate(9)
            ->appends(['opage' => \request()->opage, 'rpage' => \request()->rpage]);

        $freezeOrders = Order::query()->where('seller_id', $userId)
            ->where(function ($query) {
                $query->whereNotNull('freeze_expired_at')
                    ->orWhere(function ($subQuery) {
                        $subQuery->whereNull('freeze_expired_at')->where('product_storehouse_status', 0);
                    });
            })
            ->latest()
            ->paginate(9, ['*'], 'opage')
            ->appends(['page' => \request()->page, 'rpage' => \request()->rpage]);

        $rechargeList = Wallet::query()->where('user_id', $userId)->latest()->paginate(9, ['*'], 'rpage')
            ->appends(['page' => \request()->page, 'opage' => \request()->opage]);
        $shop = $user->shop;
        
        
        
        $paymentList = Payment::orderBy('created_at', 'desc')->where('t_type',1)->where('seller_id',Auth::user()->id)->paginate(15);
        
        return view('seller.money_withdraw_requests.index', compact('paymentList','seller_withdraw_requests', 'freezeOrders', 'rechargeList', 'balance', 'shop'));
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
     
         public function store(Request $request)
    {
        app(\App\Services\WithdrawalService::class)->apply($request, Auth::user());
        flash(translate('Request has been sent successfully'))->success();
        return redirect()->route('seller.money_withdraw_requests.index');
    }

    public function store234(Request $request)
    {
        $user = Auth::user();
  
        $type = $request->type;
        if( $type == 1 )
        {
                if ($request->amount > $user->balance) {
                    flash(translate('You do not have enough balance to send withdraw request'))->error();
                    return back();
                }
                $exits = SellerWithdrawRequest::where('status', '0')->where('type',1)->where('user_id', $user->id)->count();
                
                if ($exits !== 0) {
                    flash(translate('withdraw exited'))->error();
                    return back();
                }
                $seller_withdraw_request = new SellerWithdrawRequest;
                $seller_withdraw_request->user_id = $user->id;
                $seller_withdraw_request->amount = $request->amount;
                $seller_withdraw_request->message = $request->message;
                $seller_withdraw_request->status = '0';
                $seller_withdraw_request->viewed = '0';
                if ($seller_withdraw_request->save()) {//扣除余额
                    $userModel = User::find($user->id);
                    $userModel->balance = $user->balance-$request->amount;
                    $userModel->save();
                    flash(translate('Request has been sent successfully'))->success();
                    return redirect()->route('seller.money_withdraw_requests.index');
                } else {
                    flash(translate('Something went wrong'))->error();
                    return back();
                }
        }
        elseif ( $type == 2 )
        {
            
            if ($request->amount > $user->shop->bzj_money) {
                    flash(translate('You do not have enough guarantee balance to send withdraw request'))->error();
                    return back();
                }
                $exits = SellerWithdrawRequest::where('status', '0')->where('type',2)->where('user_id', $user->id)->count();
                
                if ($exits !== 0) 
                {
                    flash(translate('withdraw exited'))->error();
                    return back();
                }
                $seller_withdraw_request = new SellerWithdrawRequest;
                $seller_withdraw_request->user_id = $user->id;
                $seller_withdraw_request->amount = $request->amount;
                $seller_withdraw_request->message = $request->message;
                $seller_withdraw_request->status = '0';
                $seller_withdraw_request->viewed = '0';
                $seller_withdraw_request->type = 2;
                if ($seller_withdraw_request->save()) {//扣除余额
                    $userModel = Shop::find($user->shop->id);
                    $userModel->bzj_money = $userModel->bzj_money-$request->amount;
                    $userModel->save();
                    flash(translate('Request has been sent successfully'))->success();
                    return redirect()->route('seller.money_withdraw_requests.index');
                } else {
                    flash(translate('Something went wrong'))->error();
                    return back();
                }
                
                
        }
    }
}
