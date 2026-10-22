<?php

namespace App\Http\Controllers\Seller;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Wallet;
use Illuminate\Http\Request;
use App\Models\SellerWithdrawRequest;
use App\Models\User;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;
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
        $seller_withdraw_requests = SellerWithdrawRequest::where('user_id', $userId)->latest()->orderByDesc('id')->paginate(9)
            ->withQueryString();

        $freezeOrders = Order::query()->where('seller_id', $userId)
            ->where(function ($query) {
                $query->whereNotNull('freeze_expired_at')
                    ->orWhere(function ($subQuery) {
                        $subQuery->whereNull('freeze_expired_at')->where('product_storehouse_status', 0);
                    });
            })
            ->latest()
            ->orderByDesc('id')
            ->paginate(9, ['*'], 'opage')
            ->withQueryString();

        $rechargeList = Wallet::query()->where('user_id', $userId)->latest()->orderByDesc('id')->paginate(9, ['*'], 'rpage')
            ->withQueryString();
        $shop = $user->shop;
        
        
        
        $paymentList = Payment::where('t_type', 1)->where('seller_id', $userId)->latest()->orderByDesc('id')
            ->paginate(15, ['*'], 'ppage')->withQueryString();

        // The transaction history includes every source, even when there are no recharges.
        // Keep source records distinct: a paid withdrawal can also have a payment receipt.
        $history = DB::table('wallets')->where('user_id', $userId)
            ->select('id', 'created_at', 'amount', 'payment_method')
            ->selectRaw("'recharge' as source, approval as status")
            ->unionAll(DB::table('seller_withdraw_requests')->where('user_id', $userId)
                ->select('id', 'created_at', 'amount')->selectRaw("w_type as payment_method, 'withdrawal' as source, status"))
            ->unionAll(DB::table('payments')->where('seller_id', $userId)->where('t_type', 1)
                ->select('id', 'created_at', 'amount', 'payment_method')->selectRaw("'payment' as source, 1 as status"));
        $walletHistory = DB::query()->fromSub($history, 'wallet_history')
            ->orderByDesc('created_at')->orderBy('source')->orderByDesc('id')
            ->paginate(15, ['*'], 'hpage')->withQueryString()->fragment('wallet-history');
        
        return view('seller.money_withdraw_requests.index', compact('walletHistory', 'paymentList','seller_withdraw_requests', 'freezeOrders', 'rechargeList', 'balance', 'shop'));
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
        return $this->store($request);
    }
}
