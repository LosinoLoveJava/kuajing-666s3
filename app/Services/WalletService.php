<?php

namespace App\Services;

use App\Models\ManualPaymentMethod;
use App\Models\Shop;
use App\Models\Upload;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public static function onlineMethods()
    {
        return collect(['paypal' => 'paypal_payment', 'stripe' => 'stripe_payment'])
            ->filter(function ($setting) { return (int) get_setting($setting) === 1; })->keys()->all();
    }

    public function startOnline(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->user_type === 'customer' && !$user->banned, 403);
        $data = $request->validate(['amount' => self::amountRules(), 'payment_option' => 'required|in:'.implode(',', self::onlineMethods())]);
        abort_unless(in_array($data['payment_option'], self::onlineMethods(), true), 422);
        $wallet = new Wallet;
        $wallet->user_id = $user->id;
        $wallet->amount = $data['amount'];
        $wallet->payment_method = $data['payment_option'];
        $wallet->payment_details = '';
        $wallet->approval = 0;
        $wallet->offline_payment = 0;
        $wallet->type = 1;
        $wallet->operator_id = 0;
        $wallet->save();
        return ['wallet_id' => $wallet->id, 'amount' => $data['amount'], 'payment_method' => $data['payment_option']];
    }

    public function completeOnline(User $user, array $data, $proof)
    {
        $reference = null;
        $amount = null;
        if ($proof instanceof \PayPalHttp\HttpResponse && $data['payment_method'] === 'paypal') {
            abort_unless(($proof->result->status ?? '') === 'COMPLETED'
                && ($proof->result->id ?? '') === session('wallet_paypal_order'), 422);
            $capture = $proof->result->purchase_units[0]->payments->captures[0] ?? null;
            abort_unless($capture && $capture->status === 'COMPLETED' && $capture->amount->currency_code === 'USD', 422);
            $reference = 'paypal:'.$capture->id;
            $amount = $capture->amount->value;
        } elseif ($proof instanceof \Stripe\Checkout\Session && $data['payment_method'] === 'stripe') {
            abort_unless($proof->payment_status === 'paid' && $proof->id === session('wallet_stripe_session')
                && strtolower($proof->currency) === 'usd', 422);
            $reference = 'stripe:'.$proof->id;
            $amount = bcdiv((string) $proof->amount_total, '100', 2);
        }
        abort_unless($reference && $amount !== null, 422, 'Verified payment required.');
        return DB::transaction(function () use ($user, $data, $proof, $reference, $amount) {
            $wallet = Wallet::whereKey($data['wallet_id'] ?? 0)->lockForUpdate()->firstOrFail();
            abort_unless((int) $wallet->user_id === (int) $user->id && (int) $wallet->offline_payment === 0
                && $wallet->payment_method === $data['payment_method'] && bccomp((string) $wallet->amount, (string) $amount, 2) === 0, 422);
            if ((int) $wallet->approval === 1) return $wallet;
            abort_unless((int) $wallet->approval === 0, 409);
            $account = User::whereKey($wallet->user_id)->lockForUpdate()->firstOrFail();
            // A provider transaction is unique across every account and attempt.
            $wallet->request_key = hash('sha256', $reference);
            $wallet->transaction_reference = $reference;
            $wallet->payment_details = json_encode($proof);
            $wallet->approval = 1;
            $wallet->reviewed_at = now();
            $wallet->save();
            $account->balance = bcadd((string) $account->balance, (string) $wallet->amount, 2);
            $account->save();
            return $wallet;
        }, 3);
    }

    public static function amountRules()
    {
        return ['required', 'numeric', 'regex:/^[0-9]{1,10}(\.[0-9]{1,2})?$/D', 'min:0.01'];
    }

    public static function methods()
    {
        return ManualPaymentMethod::all()->filter(function ($method) {
            return stripos((string) $method->heading, 'usdt') === false
                && stripos((string) $method->heading, 'tether') === false;
        });
    }

    public function submit(Request $request, User $principal)
    {
        abort_unless(in_array($principal->user_type, ['customer', 'seller'], true) && !$principal->banned, 403);
        $rules = [
            'type' => 'nullable|integer|in:1', 'amount' => self::amountRules(),
            'trx_id' => 'required|string|max:200', 'photo' => 'nullable|integer',
        ];
        if ($principal->user_type === 'seller') {
            $rules += [
                'recharge_method' => 'required|in:Bank,IBAN',
                'bank_name' => 'nullable|string|max:2000', 'account_holder' => 'nullable|string|max:2000',
                'bank_account' => 'nullable|string|max:2000',
                'iban_country' => 'nullable|string|max:2000',
                'iban_number' => 'nullable|string|max:2000',
                'iban_bic' => 'nullable|string|max:2000',
                'request_token' => 'required_without:trx_id|nullable|uuid',
                'amount_aed' => ['nullable', 'numeric', 'regex:/^[0-9]{1,10}(\.[0-9]{1,2})?$/D', 'min:0.02'],
            ];
            $rules['trx_id'] = 'nullable|string|max:200';
            if ($request->filled('amount_aed')) $rules['amount'] = 'exclude';
        } else {
            $rules['payment_option'] = 'required|string|max:200';
        }
        $data = $request->validate($rules);
        $methods = self::methods();
        if ($methods->isEmpty()) {
            throw ValidationException::withMessages(['payment_option' => 'No bank transfer instructions are configured. Please contact support.']);
        }
        if (!empty($data['photo']) && !Upload::whereKey($data['photo'])->where('user_id', $principal->id)->where('type', 'image')->exists()) {
            throw ValidationException::withMessages(['photo' => 'Please select an image uploaded by your account.']);
        }
        $method = $data['recharge_method'] ?? $data['payment_option'];
        $details = [];
        if ($principal->user_type === 'seller') {
            $details = ['Bank: '.($data['bank_name'] ?? ''), 'Account holder: '.($data['account_holder'] ?? ''),
                'Account number: '.($data['bank_account'] ?? '')];
            if ($method === 'IBAN') {
                $details[] = 'IBAN: '.($data['iban_number'] ?? '');
                $details[] = 'Country: '.($data['iban_country'] ?? '');
                $details[] = 'SWIFT / BIC: '.($data['iban_bic'] ?? '');
            }
            if (isset($data['amount_aed'])) {
                // AED entry uses the same fixed rate as the existing seller form. Never trust its hidden USD amount.
                $data['amount'] = bcadd(bcdiv($data['amount_aed'], '3.67', 8), '0.005', 2);
                if (bccomp($data['amount'], '0.01', 2) < 0) {
                    throw ValidationException::withMessages(['amount_aed' => 'Amount is too small.']);
                }
                $details[] = 'AED: '.$data['amount_aed'].' (1 USD = 3.67 AED)';
            }
        } elseif (!$methods->contains('heading', $method)) {
            throw ValidationException::withMessages(['payment_option' => 'Please select a configured payment method.']);
        }
        $reference = trim($data['trx_id'] ?? '');
        if ($reference === '' && $principal->user_type !== 'seller') {
            throw ValidationException::withMessages(['trx_id' => 'Please enter the bank transaction reference.']);
        }
        $details[] = 'Transaction ID: '.$reference;
        // One bank transfer must never become two deposits, even after its first review.
        $key = $reference !== '' ? hash('sha256', 'offline|'.$principal->id.'|'.strtolower($reference))
            : hash('sha256', 'offline-form|'.$principal->id.'|'.$data['request_token']);
        return DB::transaction(function () use ($principal, $data, $method, $details, $reference, $key) {
            $user = User::whereKey($principal->id)->lockForUpdate()->firstOrFail();
            abort_if($user->banned, 403);
            if ($existing = Wallet::where('request_key', $key)->first()) {
                return $existing;
            }
            $wallet = new Wallet;
            $wallet->user_id = $user->id;
            $wallet->amount = $data['amount'];
            $wallet->payment_method = $method;
            $wallet->payment_details = implode(' | ', $details);
            $wallet->transaction_reference = $reference;
            $wallet->request_key = $key;
            $wallet->approval = 0;
            $wallet->offline_payment = 1;
            $wallet->reciept = $data['photo'] ?? null;
            $wallet->operator_id = 0;
            $wallet->type = 1;
            $wallet->save();
            return $wallet;
        }, 3);
    }

    public function review(Request $request)
    {
        $this->authorizeReviewer($request);
        $data = $request->validate(['id' => 'required|integer', 'status' => 'required|integer|in:1,2', 'remarks' => 'nullable|string|max:2000']);
        return DB::transaction(function () use ($request, $data) {
            $wallet = Wallet::whereKey($data['id'])->lockForUpdate()->firstOrFail();
            abort_unless((int) $wallet->offline_payment === 1 && (int) $wallet->approval === 0
                && $wallet->payment_method !== 'seller_order_reward', 409, 'Recharge has already been reviewed.');
            abort_unless(bccomp((string) $wallet->amount, '0', 2) > 0, 409, 'Invalid recharge amount.');
            if ((int) $data['status'] === 1) {
                $account = (int) $wallet->type === 2
                    ? Shop::where('user_id', $wallet->user_id)->lockForUpdate()->firstOrFail()
                    : User::whereKey($wallet->user_id)->lockForUpdate()->firstOrFail();
                $field = (int) $wallet->type === 2 ? 'bzj_money' : 'balance';
                $account->$field = bcadd((string) $account->$field, (string) $wallet->amount, 2);
                $account->save();
            }
            $wallet->approval = $data['status'];
            $wallet->operator_id = $request->user()->id;
            $wallet->remarks = $data['remarks'] ?? '';
            $wallet->reviewed_at = now();
            $wallet->save();
            return $wallet;
        }, 3);
    }

    public function directCredit(Request $request)
    {
        $this->authorizeReviewer($request);
        $data = $request->validate(['user_id' => 'required|integer', 'type' => 'nullable|integer|in:1',
            'amount' => self::amountRules(), 'request_token' => 'required|uuid', 'remarks' => 'nullable|string|max:2000']);
        return DB::transaction(function () use ($request, $data) {
            $user = User::whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            abort_unless(in_array($user->user_type, ['customer', 'seller'], true), 422);
            $key = hash('sha256', 'admin|'.$request->user()->id.'|'.$data['request_token']);
            if ($existing = Wallet::where('request_key', $key)->first()) {
                return $existing;
            }
            $wallet = new Wallet;
            $wallet->user_id = $user->id;
            $wallet->amount = $data['amount'];
            $wallet->payment_method = 'Admin credit';
            $wallet->payment_details = '';
            $wallet->approval = 1;
            $wallet->offline_payment = 1;
            $wallet->type = 1;
            $wallet->request_key = $key;
            $wallet->operator_id = $request->user()->id;
            $wallet->remarks = $data['remarks'] ?? '';
            $wallet->reviewed_at = now();
            $wallet->save();
            $user->balance = bcadd((string) $user->balance, (string) $wallet->amount, 2);
            $user->save();
            return $wallet;
        }, 3);
    }

    private function authorizeReviewer(Request $request)
    {
        abort_unless($request->user() && in_array($request->user()->user_type, ['admin', 'staff'], true), 403);
    }
}
