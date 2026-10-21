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
        abort_unless($request->user() && in_array($request->user()->user_type, ['admin', 'staff'], true), 403);
        $data = $request->validate(['remarks' => 'nullable|string|max:2000']);
        return DB::transaction(function () use ($id, $approve, $data) {
            $withdrawal = SellerWithdrawRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $withdrawal->w_type === 4 && (int) $withdrawal->status === 0, 409, 'Withdrawal has already been reviewed.');
            if ($approve) {
                $payment = new Payment;
                $payment->seller_id = $withdrawal->user_id;
                $payment->amount = $withdrawal->amount;
                $payment->payment_method = 'iban_payment';
                $payment->payment_details = json_encode($withdrawal->iban_details);
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
            $withdrawal->save();
            return $withdrawal;
        });
    }

    public function apply(Request $request, User $principal, $customer = false)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'regex:/^[0-9]{1,10}(\.[0-9]{1,2})?$/D', 'min:0.01'],
            'w_type' => 'required|integer|in:1,2,3,4',
            // Deposits are retired. Historical requests remain reviewable above.
            'type' => 'nullable|integer|in:1',
            'message' => 'nullable|string|max:2000',
            'iban_country' => 'required_if:w_type,4|nullable|string|size:2',
            'iban_holder' => 'required_if:w_type,4|nullable|string|max:200',
            'iban_bank' => 'required_if:w_type,4|nullable|string|max:200',
            'iban_number' => 'required_if:w_type,4|nullable|string|max:64',
            'iban_bic' => ['nullable', 'string', 'regex:/^[a-zA-Z]{6}[a-zA-Z0-9]{2}([a-zA-Z0-9]{3})?$/D'],
        ]);
        $snapshot = null;
        if ((int) $data['w_type'] === 4) {
            if (!Iban::valid($data['iban_number'], $data['iban_country'])) {
                throw ValidationException::withMessages(['iban_number' => 'Invalid IBAN or account country.']);
            }
            $snapshot = [
                'country' => strtoupper($data['iban_country']), 'holder' => $data['iban_holder'],
                'bank' => $data['iban_bank'], 'iban' => Iban::normalize($data['iban_number']),
                'bic' => strtoupper($data['iban_bic'] ?? ''),
            ];
        }
        return DB::transaction(function () use ($principal, $customer, $data, $snapshot) {
            $user = User::whereKey($principal->id)->lockForUpdate()->firstOrFail();
            $type = 1;
            $account = $user;
            $field = 'balance';
            if (bccomp((string) $account->$field, $data['amount'], 2) < 0) {
                throw ValidationException::withMessages(['amount' => 'Insufficient balance.']);
            }
            if (!$customer && SellerWithdrawRequest::where('user_id', $user->id)->where('type', $type)->where('status', 0)->exists()) {
                throw ValidationException::withMessages(['amount' => 'A withdrawal is already awaiting approval.']);
            }
            $withdrawal = new SellerWithdrawRequest;
            $withdrawal->user_id = $user->id;
            $withdrawal->amount = $data['amount'];
            $withdrawal->message = $data['message'] ?? '';
            $withdrawal->status = 0;
            $withdrawal->viewed = 0;
            $withdrawal->type = $type;
            $withdrawal->t_type = $customer ? 2 : 1;
            $withdrawal->w_type = $data['w_type'];
            $withdrawal->remarks = '';
            $withdrawal->iban_details = $snapshot;
            $withdrawal->save();
            $account->$field = bcsub((string) $account->$field, $data['amount'], 2);
            $account->save();
            return $withdrawal;
        });
    }
}
