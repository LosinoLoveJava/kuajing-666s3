<?php

namespace App\Services;

use App\Models\SellerPackage;
use App\Models\SellerPackagePayment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Account provisioning by authenticated management, never a public SMS bypass. */
class ManagedPhoneAccounts
{
    public function authorize(User $operator = null, User $subject = null)
    {
        abort_unless($operator && in_array($operator->user_type, ['admin', 'staff', 'salesman'], true), 403);
        if ($subject && $operator->user_type === 'salesman') {
            abort_unless((int) $subject->pid === (int) $operator->id, 403);
        }
    }

    private function phone(Request $request, User $user = null)
    {
        $data = $request->validate(['phone' => 'required|string|max:40', 'country' => 'nullable|string|size:2', 'email' => 'prohibited']);
        $phone = app(PhoneNumber::class)->normalize($data['phone'], $data['country'] ?? null);
        $existing = app(PhoneNumber::class)->user($phone);
        if ($existing && (!$user || $existing->id !== $user->id)) {
            throw ValidationException::withMessages(['phone' => translate('Phone already exists.')]);
        }
        return $phone;
    }

    private function newUser(Request $request, User $operator, $role, $virtual, $name)
    {
        $request->validate(['password' => 'required|string|min:8|max:100|confirmed']);
        $phone = $this->phone($request);
        $user = new User;
        $user->name = $name;
        $user->user_type = $role;
        $user->is_virtual = $virtual ? 1 : 0;
        $user->phone = $phone;
        $user->phone_normalized = $phone;
        // Trusted back-office provisioning: no SMS/mail is sent; password login is available.
        $user->phone_verified_at = now();
        $user->password = Hash::make($request->password);
        foreach (['tpwd', 'usdt_address', 'node', 'deep'] as $column) { $user->$column = ''; }
        if ($operator->user_type === 'salesman') {
            $user->pid = $operator->id;
            if ($role === 'customer') { $user->referred_by = $operator->id; }
        }
        return $user;
    }

    public function createSeller(Request $request, User $operator = null, $virtual = true)
    {
        $this->authorize($operator);
        $data = $request->validate([
            'account_name' => 'required|string|max:191', 'name' => 'required|string|max:191',
            'address' => 'required|string|max:500',
            'certtype' => ['nullable', Rule::in(['idcard', 'passport', 'driving license', 'social security card'])],
            'identity_card_front' => 'required|integer', 'identity_card_back' => 'nullable|integer',
        ]);
        return DB::transaction(function () use ($request, $operator, $virtual, $data) {
            $package = app(AccountMembership::class)->sellerPackage();
            if (!$package) {
                throw ValidationException::withMessages(['name' => translate('Sorry! Something went wrong.')]);
            }
            // Only the operator's images or unclaimed public-application images may be used.
            $ids = array_values(array_unique(array_filter([$data['identity_card_front'], $data['identity_card_back'] ?? null])));
            $images = DB::table('uploads')->whereIn('id', $ids)->where('type', 'image')
                ->whereIn('user_id', [0, $operator->id])->lockForUpdate()->get();
            foreach (['identity_card_front', 'identity_card_back'] as $field) {
                if (!empty($data[$field]) && !$images->contains('id', $data[$field])) {
                    throw ValidationException::withMessages([$field => translate('Choose File')]);
                }
            }
            $user = $this->newUser($request, $operator, 'seller', $virtual, $data['account_name']);
            $user->identity_card_front = $data['identity_card_front'];
            $user->identity_card_back = $data['identity_card_back'] ?? '';
            $user->certtype = $data['certtype'] ?? 'idcard';
            $user->save();
            $shop = new Shop;
            $shop->user_id = $user->id;
            $shop->name = $data['name'];
            $shop->address = $data['address'];
            $shop->phone = $user->phone;
            $shop->slug = preg_replace('/\s+/', '-', $data['name']).'-'.$user->id;
            $shop->seller_package_id = $package->id;
            $shop->product_upload_limit = $package->product_upload_limit;
            $shop->package_invalid_at = AccountMembership::PERMANENT_UNTIL;
            $shop->verification_status = 0;
            $shop->seller_spread_package_id = 0;
            $shop->product_spread_limit = 0;
            $shop->bzj_money = 0;
            $shop->usdt_address = '';
            $shop->views_up_time = 0;
            $shop->save();
            $payment = new SellerPackagePayment;
            $payment->user_id = $user->id;
            $payment->seller_package_id = $package->id;
            $payment->payment_method = 'free';
            $payment->payment_details = '';
            $payment->approval = 1;
            $payment->offline_payment = 0;
            $payment->save();
            // Management-owned media stays reusable; claim anonymous submitted images only.
            DB::table('uploads')->where('user_id', 0)->whereIn('id', $ids)->update(['user_id' => $user->id]);
            return $user;
        });
    }

    public function createCustomer(Request $request, User $operator = null)
    {
        $this->authorize($operator);
        $data = $request->validate(['name' => 'required|string|max:191', 'balance' => 'nullable|numeric|min:0']);
        return DB::transaction(function () use ($request, $operator, $data) {
            $user = $this->newUser($request, $operator, 'customer', true, $data['name']);
            $user->balance = $data['balance'] ?? 0;
            $user->save();
            return $user;
        });
    }

    public function updateSeller(Request $request, Shop $shop, User $operator = null)
    {
        $user = $shop->user;
        $this->authorize($operator, $user);
        abort_unless($user && $user->user_type === 'seller', 404);
        $data = $request->validate(['name' => 'required|string|max:191', 'password' => 'nullable|string|min:8|max:100',
            'tpwd' => 'nullable|string|max:100', 'views' => 'nullable|integer|min:0']);
        return DB::transaction(function () use ($request, $shop, $user, $data) {
            $phone = $this->phone($request, $user);
            if ($user->phone_normalized !== $phone || !empty($data['password'])) {
                $user->remember_token = Str::random(60);
                $user->tokens()->delete();
            }
            $user->name = $data['name'];
            $user->phone = $phone;
            $user->phone_normalized = $phone;
            $user->phone_verified_at = now();
            if (!empty($data['password'])) { $user->password = Hash::make($data['password']); }
            if (!empty($data['tpwd'])) { $user->tpwd = md5($data['tpwd']); }
            $user->save();
            $shop->phone = $phone;
            if (isset($data['views'])) { $shop->views = $data['views']; }
            $shop->save();
            return $user;
        });
    }
}
