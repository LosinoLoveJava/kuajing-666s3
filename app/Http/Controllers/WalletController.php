<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Models\Shop;
use App\Models\SellerWithdrawRequest;
use Auth;
use Session;
use function array_column;
use function back;
use function date;
use function dd;
use function explode;
use function flash;
use function redirect;
use function strtotime;
use function translate;

class WalletController extends Controller
{
    public function index() {
        $wallets = Wallet::where('user_id', Auth::user()->id)->latest()->paginate(9, ['*'], 'rpage')->withQueryString();

        $user = Auth::user();
        $userId = $user->id;
        $seller_withdraw_requests = SellerWithdrawRequest::where('user_id', $userId)->latest()->paginate(9, ['*'], 'wpage')->withQueryString();

        $balance = $user->balance;
        return view('frontend.user.wallet.index', compact('wallets', 'seller_withdraw_requests', 'balance'));
    }

    public function do_money_withdraw_request(Request $request)
    {
        app(\App\Services\WithdrawalService::class)->apply($request, Auth::user(), true);
        flash(translate('Request has been sent successfully'))->success();
        return redirect()->route('wallet.index');
    }

    public function recharge(Request $request)
    {
        $data = app(\App\Services\WalletService::class)->startOnline($request);
        $request->session()->put('payment_type', 'wallet_payment');
        $request->session()->put('payment_data', $data);
        $controller = $data['payment_method'] === 'paypal' ? new Payment\PaypalController : new Payment\StripeController;
        return $controller->pay($request);
    }

    public function wallet_payment_done($payment_data, $payment_details)
    {
        abort_unless(Auth::user() && is_array($payment_data), 422);
        app(\App\Services\WalletService::class)->completeOnline(Auth::user(), $payment_data, $payment_details);
        Session::forget(['payment_data', 'payment_type', 'wallet_paypal_order', 'wallet_stripe_session']);
        flash(translate('Payment completed'))->success();
        return redirect()->route('wallet.index');
    }

    public function offline_recharge(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);
        $service = app(\App\Services\WalletService::class);
        if (in_array($user->user_type, ['admin', 'staff'], true)) {
            $service->directCredit($request);
            flash(translate('Recharge has been done'))->success();
            return back();
        }
        $service->submit($request, $user);
        flash(translate('Offline Recharge has been done. Please wait for response.'))->success();
        return redirect()->route($user->user_type === 'seller' ? 'seller.money_withdraw_requests.index' : 'wallet.index');
    }

    public function offline_recharge_request( Request $request ) {
        $name = $request->name ?? '';
        $operator = $request->operator ?? '';
        $date = $request->date ?? '';
        $wallets = Wallet::with(['user', 'operator'])->where('offline_payment', 1);
        if ($name){
            $users = User::where('name', 'like',"%".$name."%")->get()->toArray();
            $ids = array_column($users, 'id');
            $wallets = $wallets->whereIn('user_id', $ids);
        }

        if ($date) {
            $wallets = $wallets->whereDate('created_at', '>=', date('Y-m-d', strtotime(explode(" to ", $date)[0])))->whereDate('created_at', '<=', date('Y-m-d', strtotime(explode(" to ", $date)[1])));
        }

        if ($operator){
            if ($operator == 'admin'){
                $users = User::where('user_type', 'admin')->get()->toArray();
                $ids = array_column($users, 'id');
                $wallets = $wallets->whereIn('operator_id', $ids);
            }else{
                $users = User::where('name', 'like',"%".$operator."%")->get()->toArray();
                $ids = array_column($users, 'id');
                $wallets = $wallets->whereIn('operator_id', $ids);
            }
        }

        if ( Auth::user()->user_type == 'salesman' )
        {
            $userIds = User::where('pid', Auth::user()->id)->get()->toArray();
            $wallets = Wallet::with(['user', 'operator'])->where('offline_payment', 1)->whereIn('user_id', array_column($userIds, 'id'))->latest()->paginate(10);
            return view('manual_payment_methods.wallet_request_salesman', compact('wallets'));
        }

        $wallets = $wallets->latest()->paginate(10);
        return view('manual_payment_methods.wallet_request', compact('wallets', 'name', 'operator', 'date'));
    }

    public function offline_recharge_request_by_seller( Request $request ) {
//        dd($request->user_id);
        abort_unless(in_array($request->user()->user_type, ['admin', 'staff'], true), 403);
        $wallets = Wallet::with(['user', 'operator'])->where('offline_payment', 1)->where('user_id', $request->user_id)->latest()->paginate(20)->withQueryString();
        return view('manual_payment_methods.wallet_request_by_seller', compact('wallets'));
    }

    public function updateApproved(Request $request)
    {
        app(\App\Services\WalletService::class)->review($request);
        return 1;
    }

    public function updateApproved345(Request $request)
    {
        return $this->updateApproved($request);
    }
}
