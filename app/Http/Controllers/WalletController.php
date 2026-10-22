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
        $wallets = Wallet::where('user_id', Auth::user()->id)->latest()->paginate(9);

        $user = Auth::user();
        $userId = $user->id;
        $seller_withdraw_requests = SellerWithdrawRequest::where('user_id', $userId)->latest()->paginate(9)
            ->appends([ 'opage' => \request()->opage, 'rpage' => \request()->rpage ]);

        return view('frontend.user.wallet.index', compact('wallets', 'seller_withdraw_requests'));
    }

    public function do_money_withdraw_request(Request $request)
    {
        app(\App\Services\WithdrawalService::class)->apply($request, Auth::user(), true);
        flash(translate('Request has been sent successfully'))->success();
        return redirect()->route('wallet.index');
    }

    public function recharge( Request $request ) {
        $data['amount'] = $request->amount;
        $data['payment_method'] = $request->payment_option;

        $request->session()->put('payment_type', 'wallet_payment');
        $request->session()->put('payment_data', $data);

        $request->session()->put('payment_type', 'wallet_payment');
        $request->session()->put('payment_data', $data);

        $decorator = __NAMESPACE__ . '\\Payment\\' . str_replace(' ', '', ucwords(str_replace('_', ' ', $request->payment_option))) . "Controller";
        if ( class_exists($decorator) )
        {
            return ( new $decorator )->pay($request);
        }
    }

    public function wallet_payment_done( $payment_data, $payment_details ) {
        $user = Auth::user();
        $user->balance = $user->balance + $payment_data['amount'];
        $user->save();

        $wallet = new Wallet;
        $wallet->user_id = $user->id;
        $wallet->amount = $payment_data['amount'];
        $wallet->payment_method = $payment_data['payment_method'];
        $wallet->payment_details = $payment_details;
        $wallet->save();

        Session::forget('payment_data');
        Session::forget('payment_type');

        flash(translate('Payment completed'))->success();
        return redirect()->route('wallet.index');
    }

    public function offline_recharge( Request $request ) {
        $request->validate(['type' => 'nullable|integer|in:1']);
        $sellerDetails = null;
        if (Auth::user()->user_type === 'seller') {
            $data = $request->validate([
                'recharge_method' => 'required|in:Bank,IBAN',
                'bank_name' => 'required|string|max:200',
                'account_holder' => 'required|string|max:200',
                'bank_account' => 'required_if:recharge_method,Bank|nullable|string|max:100',
                'iban_country' => 'required_if:recharge_method,IBAN|nullable|string|size:2',
                'iban_number' => 'required_if:recharge_method,IBAN|nullable|string|max:64',
                'iban_bic' => ['nullable', 'string', 'regex:/^[a-zA-Z]{6}[a-zA-Z0-9]{2}([a-zA-Z0-9]{3})?$/D'],
                'amount' => ['required', 'numeric', 'regex:/^[0-9]{1,10}(\.[0-9]{1,2})?$/D', 'min:0.01'],
                'trx_id' => 'required|string|max:200',
                'photo' => 'nullable|integer',
            ], ['*' => translate('Please check this field: :attribute')], [
                'recharge_method' => translate('Payment method'),
                'bank_name' => translate('Bank Name'),
                'account_holder' => translate('Account holder'),
                'bank_account' => translate('Bank Account Number'),
                'iban_country' => translate('Account country'),
                'iban_number' => 'IBAN', 'iban_bic' => 'SWIFT / BIC',
                'amount' => translate('Amount'), 'trx_id' => translate('Transaction ID'),
                'photo' => translate('Photo'),
            ]);
            $details = [translate('Bank Name').': '.$data['bank_name'], translate('Account holder').': '.$data['account_holder']];
            if ($data['recharge_method'] === 'IBAN') {
                if (!\App\Services\Iban::valid($data['iban_number'], $data['iban_country'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['iban_number' => translate('Invalid IBAN or account country.')]);
                }
                $details[] = translate('Account country').': '.strtoupper($data['iban_country']);
                $details[] = 'IBAN: '.\App\Services\Iban::normalize($data['iban_number']);
                $details[] = 'SWIFT / BIC: '.strtoupper($data['iban_bic'] ?? '');
            } else {
                $details[] = translate('Bank Account Number').': '.$data['bank_account'];
            }
            $details[] = translate('Transaction ID').': '.$data['trx_id'];
            $details[] = 'USD: '.bcadd((string) $data['amount'], '0', 2);
            $details[] = 'AED (1 USD = 3.67 AED): '.bcadd(bcmul((string) $data['amount'], '3.67', 4), '0.005', 2);
            $sellerDetails = implode(' | ', $details);
        }
        if (Auth::user()->user_type=='admin'){//如果是管理员充值
            $user = $user = User::where('id', $request->user_id)->first();
            if (!$user) {
                flash(translate('Please select one customer'))->warning();
                return redirect()->route('admin.customers');
            }
            $wallet = new Wallet;
            $wallet->user_id = $user->id;
            $wallet->amount = $request->amount;
            $wallet->payment_method = $request->payment_option;
            $wallet->payment_details = '';
            $wallet->approval = 1;//直接状态为已审核
            $wallet->offline_payment = 1;
            $wallet->reciept = '';
            $wallet->operator_id = Auth::user()->id;
            $wallet->type = $request->type ?? 1;
            $wallet->save();
            $user->balance = $user->balance + $wallet->amount;
            $user->save();
            flash(translate('Recharge has been done'))->success();
            return redirect()->route('customers.index');
        }

        $wallet = new Wallet;
        $wallet->user_id = Auth::user()->id;
        $wallet->amount = $request->amount;
        $wallet->payment_method = $sellerDetails !== null ? $request->recharge_method : $request->payment_option;
        $wallet->payment_details = $sellerDetails ?? $request->trx_id;
        $wallet->approval = 0;
        $wallet->offline_payment = 1;
        $wallet->reciept = $request->photo;
        $wallet->type = $request->type ?? 1;
        $wallet->save();
        flash(translate('Offline Recharge has been done. Please wait for response.'))->success();
        return redirect()->route(Auth::user()->user_type === 'seller' ? 'seller.money_withdraw_requests.index' : 'wallet.index');
    }

    public function offline_recharge_request( Request $request ) {
        $name = $request->name ?? '';
        $operator = $request->operator ?? '';
        $date = $request->date ?? '';
        $wallets = Wallet::where('offline_payment', 1);
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
            $wallets = Wallet::where('offline_payment', 1)->whereIn('user_id', array_column($userIds, 'id'))->latest()->paginate(10);
            return view('manual_payment_methods.wallet_request_salesman', compact('wallets'));
        }

        $wallets = $wallets->latest()->paginate(10);
        return view('manual_payment_methods.wallet_request', compact('wallets', 'name', 'operator', 'date'));
    }

    public function offline_recharge_request_by_seller( Request $request ) {
//        dd($request->user_id);
        $wallets = Wallet::where('offline_payment', 1)->where('user_id', $request->user_id)->get();
        return view('manual_payment_methods.wallet_request_by_seller', compact('wallets'));
    }

    public function updateApproved( Request $request ) {
        $wallet = Wallet::findOrFail($request->id);
        // Rewards are already credited atomically; they are not pending recharge requests.
        abort_if($wallet->payment_method === 'seller_order_reward', 409);
        $wallet->approval = $request->status;
        if ( $request->status == 1 )
        {
            if ( $wallet->type == 2 )
            {
                #print_r( $wallet->user->shop->toArray());
                #$wallet->user->shop->bzj_money+
                $shopid = $wallet->user->shop->id;
                $shop = Shop::findOrFail($shopid);
                $shop->bzj_money = $wallet->user->shop->bzj_money + $wallet->amount;
                $shop->save();

            }
            else
            {
                $user = $wallet->user;
                $user->balance = $user->balance + $wallet->amount;
                $user->save();
                $wallet->operator_id = Auth::user()->id;
            }
        }
        else
        {

            if ( $wallet->type == 2 )
            {
                #print_r( $wallet->user->shop->toArray());
                #$wallet->user->shop->bzj_money+
                $shopid = $wallet->user->shop->id;
                $shop = Shop::findOrFail($shopid);
                $shop->bzj_money = $wallet->user->shop->bzj_money - $wallet->amount;
                $shop->bzj_money = $shop->bzj_money < 0 ? 0 : $shop->bzj_money;
                $shop->save();

            }
            else
            {

                $user = $wallet->user;
                $user->balance = $user->balance - $wallet->amount;
                $user->save();
                $wallet->operator_id = Auth::user()->id;
            }
        }
        if ( $wallet->save() )
        {
            return 1;
        }
        return 0;
    }

    public function updateApproved345( Request $request ) {
        $wallet = Wallet::findOrFail($request->id);
        abort_if($wallet->payment_method === 'seller_order_reward', 409);
        $wallet->approval = $request->status;
        if ( $request->status == 1 )
        {
            $user = $wallet->user;
            $user->balance = $user->balance + $wallet->amount;
            $user->save();
        }
        else
        {
            $user = $wallet->user;
            $user->balance = $user->balance - $wallet->amount;
            $user->save();
        }
        if ( $wallet->save() )
        {
            return 1;
        }
        return 0;
    }
}
