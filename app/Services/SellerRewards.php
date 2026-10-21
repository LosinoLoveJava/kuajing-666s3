<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class SellerRewards
{
    public function assertSeller(User $user)
    {
        abort_unless($user->user_type === 'seller' && !$user->banned
            && Shop::where('user_id', $user->id)->exists(), 403);
    }

    private function milestones()
    {
        $milestones = config('seller_rewards.milestones', []);
        abort_unless(is_array($milestones) && count($milestones), 503, 'Seller rewards are not configured.');
        foreach ($milestones as $key => $tier) {
            abort_unless(is_string($key) && preg_match('/^[a-z0-9_]{1,64}$/D', $key)
                && is_array($tier) && isset($tier['orders'], $tier['amount'])
                && is_int($tier['orders']) && $tier['orders'] > 0
                && preg_match('/^[0-9]{1,10}(\.[0-9]{1,2})?$/D', (string) $tier['amount'])
                && bccomp((string) $tier['amount'], '0', 2) > 0, 503, 'Invalid seller reward configuration.');
        }
        uasort($milestones, function ($a, $b) { return $a['orders'] <=> $b['orders']; });
        return $milestones;
    }

    public function progress(User $user)
    {
        $this->assertSeller($user);
        $count = app(CompletedOrders::class)->count($user);
        $claims = DB::table('seller_reward_claims')->where('user_id', $user->id)->get()->keyBy('milestone');
        $tiers = [];
        foreach ($this->milestones() as $key => $tier) {
            $claim = $claims->get($key);
            $target = $claim ? (int) $claim->target_orders : $tier['orders'];
            $tiers[] = [
                'key' => $key, 'target_orders' => $target,
                'amount' => bcadd((string) ($claim ? $claim->amount : $tier['amount']), '0', 2),
                'progress_current' => min($count, $target), 'progress_target' => $target,
                'progress_percent' => round(min(100, $count * 100 / $target), 2),
                'remaining_orders' => max(0, $target - $count),
                'status' => $claim ? 'claimed' : ($count >= $target ? 'claimable' : 'in_progress'),
                'can_claim' => !$claim && $count >= $target,
                'claimed_at' => $claim ? $claim->created_at : null,
            ];
        }
        $total = '0.00';
        foreach ($claims as $claim) { $total = bcadd($total, (string) $claim->amount, 2); }
        return ['metric' => 'completed_orders', 'period' => 'lifetime', 'currency' => 'USD',
            'completed_orders' => $count, 'total_claimed_amount' => $total, 'milestones' => $tiers];
    }

    public function claim(User $principal, $key)
    {
        return DB::transaction(function () use ($principal, $key) {
            // Same lock as withdrawals: serialize all reward claims and wallet debits per seller.
            $user = User::whereKey($principal->id)->lockForUpdate()->firstOrFail();
            $this->assertSeller($user);
            $milestones = $this->milestones();
            abort_unless(isset($milestones[$key]), 404, 'Unknown reward milestone.');
            $existing = DB::table('seller_reward_claims')->where('user_id', $user->id)->where('milestone', $key)->first();
            if ($existing) { return $this->receipt($existing, true); }

            // Wallet units are the system's base currency, NOT the browser's display currency.
            // Fail closed rather than crediting USD as AED or another currency after a setting change.
            $base = DB::table('business_settings')->where('type', 'system_default_currency')->value('value');
            $code = DB::table('currencies')->where('id', $base)->value('code');
            abort_unless($code === 'USD', 503, 'USD wallet currency is required to claim seller rewards.');
            $tier = $milestones[$key];
            $count = app(CompletedOrders::class)->count($user);
            abort_unless($count >= $tier['orders'], 422, 'Reward order target has not been reached.');
            $amount = bcadd((string) $tier['amount'], '0', 2);
            $wallet = new Wallet;
            $wallet->user_id = $user->id;
            $wallet->amount = $amount;
            $wallet->payment_method = 'seller_order_reward';
            $wallet->payment_details = json_encode(['milestone' => $key, 'currency' => 'USD',
                'target_orders' => $tier['orders'], 'completed_orders' => $count]);
            $wallet->approval = 1;
            $wallet->offline_payment = 0;
            $wallet->type = 1;
            $wallet->operator_id = 0;
            $wallet->save();
            $user->balance = bcadd((string) $user->balance, $amount, 2);
            $user->save();
            $id = DB::table('seller_reward_claims')->insertGetId([
                'user_id' => $user->id, 'milestone' => $key, 'target_orders' => $tier['orders'],
                'completed_orders' => $count, 'amount' => $amount, 'currency' => 'USD',
                'wallet_id' => $wallet->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return $this->receipt(DB::table('seller_reward_claims')->where('id', $id)->first(), false);
        }, 3);
    }

    private function receipt($claim, $alreadyClaimed)
    {
        return ['claim_id' => (int) $claim->id, 'milestone' => $claim->milestone,
            'amount' => bcadd((string) $claim->amount, '0', 2), 'currency' => $claim->currency,
            'wallet_id' => (int) $claim->wallet_id, 'claimed_at' => $claim->created_at,
            'already_claimed' => $alreadyClaimed];
    }
}
