<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellerApproval
{
    public function authorize(Shop $shop, User $operator = null, $write = false)
    {
        abort_unless($operator && !$operator->banned && in_array($operator->user_type, ['admin', 'staff', 'salesman'], true), 403);
        abort_unless($shop->user && $shop->user->user_type === 'seller', 404);
        if ($operator->user_type === 'salesman') {
            abort_unless((int) $shop->user->pid === (int) $operator->id, 403);
            // Read the current setting rather than a possibly stale cached value.
            if ($write) {
                abort_unless((string) BusinessSetting::where('type', 'salesman_reviews_switch')->value('value') === '1', 403);
            }
        }
    }

    public function state(Shop $shop)
    {
        return ['id' => (int) $shop->id, 'status' => (int) $shop->verification_status,
            'version' => (int) $shop->approval_version];
    }

    public function update(Request $request, User $operator = null, $action = 'toggle', $shopId = null, $status = null)
    {
        $rules = ['version' => 'required|integer|min:0|max:4294967294'];
        if ($shopId === null) { $rules['id'] = 'required|integer|min:1'; }
        if ($status === null) { $rules['status'] = 'required|integer|in:0,1'; }
        $data = $request->validate($rules);
        $state = DB::transaction(function () use ($request, $operator, $action, $shopId, $status, $data) {
            $shop = Shop::whereKey($shopId ?? $data['id'])->lockForUpdate()->firstOrFail();
            $this->authorize($shop, $operator, true);
            if ((int) $shop->approval_version !== (int) $data['version']) {
                if (!$request->expectsJson()) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['approval' => __('seller_approval.conflict')]);
                }
                throw new HttpResponseException(response()->json([
                    'success' => false, 'conflict' => true, 'approval' => $this->state($shop),
                    'message' => __('seller_approval.conflict'),
                ], 409));
            }
            $newStatus = (int) ($status ?? $data['status']);
            $version = (int) $shop->approval_version + 1;
            $values = ['verification_status' => $newStatus, 'approval_version' => $version, 'updated_at' => now()];
            if ($action === 'reject') { $values['verification_info'] = null; }
            DB::table('shops')->where('id', $shop->id)->update($values);
            DB::table('seller_approval_logs')->insert([
                'shop_id' => $shop->id, 'user_id' => $shop->user_id, 'operator_id' => $operator->id,
                'operator_role' => $operator->user_type, 'old_status' => (int) $shop->verification_status,
                'new_status' => $newStatus, 'old_version' => (int) $shop->approval_version,
                'new_version' => $version, 'action' => $action, 'request_id' => (string) Str::uuid(),
                'ip_address' => $request->ip(), 'route' => $request->route() ? $request->route()->getName() : $request->path(),
                'created_at' => now(),
            ]);
            return ['id' => (int) $shop->id, 'status' => $newStatus, 'version' => $version];
        }, 3);
        Cache::forget('verified_sellers_id');
        return $state;
    }
}
