<?php

namespace App\Services;

use App\Models\SellerWithdrawRequest;
use App\Models\Shop;
use App\Models\User;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WithdrawalService
{
    public function reviewIban(Request $request, $id, $approve)
    {
        return $this->review($request, $id, $approve);
    }

    public function review(Request $request, $id, $approve)
    {
        abort_unless($request->user() && in_array($request->user()->user_type, ['admin', 'staff'], true), 403);
        $data = $request->validate([
            'remarks' => 'nullable|string|max:2000',
            'txn_code' => 'nullable|string|max:200',
        ]);
        return DB::transaction(function () use ($request, $id, $approve, $data) {
            $withdrawal = SellerWithdrawRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $withdrawal->status === 0, 409, 'Withdrawal has already been reviewed.');
            abort_unless(bccomp((string) $withdrawal->amount, '0', 2) > 0, 409, 'Invalid withdrawal amount.');
            if ($approve) {
                $methods = [1 => 'cash', 2 => 'bank_payment', 3 => 'usdt_payment', 4 => 'iban_payment'];
                abort_unless(isset($methods[$withdrawal->w_type]), 422, 'Unknown withdrawal method.');
                $payment = new Payment;
                $payment->seller_id = $withdrawal->user_id;
                $payment->amount = $withdrawal->amount;
                $payment->payment_method = $methods[$withdrawal->w_type];
                $payment->payment_details = json_encode([
                    'withdrawal_id' => $withdrawal->id,
                    'account' => $withdrawal->iban_details,
                    'transaction_reference' => $data['txn_code'] ?? '',
                    'operator_id' => $request->user()->id,
                ]);
                $payment->t_type = $withdrawal->t_type;
                $payment->save();
            } else {
                $account = (int) $withdrawal->type === 2
                    ? Shop::where('user_id', $withdrawal->user_id)->lockForUpdate()->firstOrFail()
                    : User::whereKey($withdrawal->user_id)->lockForUpdate()->firstOrFail();
                $field = (int) $withdrawal->type === 2 ? 'bzj_money' : 'balance';
                $account->$field = bcadd((string) $account->$field, (string) $withdrawal->amount, 2);
                $account->save();
            }
            $withdrawal->status = $approve ? 1 : 2;
            $withdrawal->viewed = 1;
            $withdrawal->remarks = $data['remarks'] ?? '';
            $withdrawal->operator_id = $request->user()->id;
            $withdrawal->reviewed_at = now();
            $withdrawal->save();
            return $withdrawal;
        }, 3);
    }

    public function apply(Request $request, User $principal, $customer = false)
    {
        abort_unless(in_array($principal->user_type, ['customer', 'seller'], true) && !$principal->banned, 403);
        $rules = [
            'amount' => ['required', 'numeric', 'regex:/^[0-9]{1,10}(\.[0-9]{1,2})?$/D', 'min:0.01'],
            'w_type' => 'required|integer|in:1,2,3,4',
            'type' => 'nullable|integer|in:1',
            'request_token' => 'nullable|uuid',
            'message' => 'nullable|string|max:2000',
            'iban_country' => 'required_if:w_type,4|nullable|string|size:2',
            'iban_holder' => 'required_if:w_type,2,4|nullable|string|max:200',
            'iban_bank' => 'required_if:w_type,2,4|nullable|string|max:200',
            'bank_account' => 'required_if:w_type,2|nullable|string|max:100',
            'iban_number' => 'required_if:w_type,4|nullable|string|max:64',
            'iban_bic' => ['nullable', 'string', 'regex:/^[a-zA-Z]{6}[a-zA-Z0-9]{2}([a-zA-Z0-9]{3})?$/D'],
        ];
        $manualReview = $principal->user_type === 'seller';
        if ($manualReview) {
            foreach (['iban_country', 'iban_holder', 'iban_bank', 'bank_account', 'iban_number', 'iban_bic'] as $field) {
                $rules[$field] = 'nullable|string|max:2000';
            }
        }
        $data = $request->validate($rules);
        $snapshot = null;
        if ((int) $data['w_type'] === 4) {
            if (!$manualReview && !Iban::valid($data['iban_number'], $data['iban_country'])) {
                throw ValidationException::withMessages(['iban_number' => 'Invalid IBAN or account country.']);
            }
            $snapshot = [
                'country' => $manualReview ? ($data['iban_country'] ?? '') : strtoupper($data['iban_country']), 'holder' => $data['iban_holder'] ?? '',
                'bank' => $data['iban_bank'] ?? '', 'iban' => $manualReview ? ($data['iban_number'] ?? '') : Iban::normalize($data['iban_number']),
                'account_number' => $data['bank_account'] ?? '', 'bic' => $manualReview ? ($data['iban_bic'] ?? '') : strtoupper($data['iban_bic'] ?? ''),
            ];
        }
        return DB::transaction(function () use ($principal, $data, $snapshot) {
            $user = User::whereKey($principal->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($user->user_type, ['customer', 'seller'], true) && !$user->banned, 403);
            $key = empty($data['request_token']) ? null : hash('sha256', $user->id.'|'.$data['request_token']);
            if ($key && $existing = SellerWithdrawRequest::where('request_key', $key)->first()) {
                return $existing;
            }
            if (SellerWithdrawRequest::where('user_id', $user->id)->where('status', 0)->exists()) {
                throw ValidationException::withMessages(['amount' => 'A withdrawal is already awaiting approval.']);
            }
            if (bccomp((string) $user->balance, $data['amount'], 2) < 0) {
                throw ValidationException::withMessages(['amount' => 'Insufficient balance.']);
            }
            if ((int) $data['w_type'] === 2) {
                $snapshot = ['method' => 'Bank', 'holder' => $data['iban_holder'] ?? '', 'bank' => $data['iban_bank'] ?? '', 'account_number' => $data['bank_account'] ?? ''];
            } elseif ((int) $data['w_type'] !== 4) {
                $snapshot = $this->profileAccount($user, (int) $data['w_type']);
            }
            $withdrawal = new SellerWithdrawRequest;
            $withdrawal->user_id = $user->id;
            $withdrawal->amount = $data['amount'];
            $withdrawal->message = $data['message'] ?? '';
            $withdrawal->status = 0;
            $withdrawal->viewed = 0;
            $withdrawal->type = 1;
            $withdrawal->t_type = $user->user_type === 'customer' ? 2 : 1;
            $withdrawal->w_type = $data['w_type'];
            $withdrawal->remarks = '';
            $withdrawal->iban_details = $snapshot;
            $withdrawal->request_key = $key;
            $withdrawal->save();
            $user->balance = bcsub((string) $user->balance, $data['amount'], 2);
            $user->save();
            return $withdrawal;
        }, 3);
    }

    private function profileAccount(User $user, $method)
    {
        if ($method === 1 && (int) $user->cash_on_delivery_status === 1) {
            return ['method' => 'Cash', 'holder' => $user->name];
        }
        if ($method === 2 && (int) $user->bank_payment_status === 1
            && trim((string) $user->bank_name) !== '' && trim((string) $user->bank_acc_name) !== '' && trim((string) $user->bank_acc_no) !== '') {
            return ['method' => 'Bank', 'holder' => $user->bank_acc_name, 'bank' => $user->bank_name,
                'account_number' => $user->bank_acc_no, 'routing_number' => $user->bank_routing_no];
        }
        if ($method === 3 && (int) $user->usdt_payment_status === 1
            && trim((string) $user->usdt_type) !== '' && trim((string) $user->usdt_address) !== '') {
            return ['method' => 'USDT', 'network' => $user->usdt_type, 'address' => $user->usdt_address];
        }
        throw ValidationException::withMessages(['w_type' => 'Please configure this withdrawal method in your profile, or select IBAN.']);
    }
}
