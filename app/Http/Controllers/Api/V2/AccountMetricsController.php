<?php

namespace App\Http\Controllers\Api\V2;

use App\Models\Currency;
use App\Services\CompletedOrders;
use Illuminate\Http\Request;

class AccountMetricsController extends Controller
{
    public function exchangeRate(Request $request)
    {
        $request->validate(['from' => 'sometimes|in:USD', 'to' => 'sometimes|in:AED']);
        $currencies = Currency::whereIn('code', ['USD', 'AED'])->get()->keyBy('code');
        $usd = $currencies->get('USD');
        $aed = $currencies->get('AED');
        if (!$usd || !$aed || !is_numeric($usd->exchange_rate) || !is_numeric($aed->exchange_rate)
            || $usd->exchange_rate <= 0 || $aed->exchange_rate <= 0) {
            return response()->json(['success' => false, 'message' => 'USD/AED exchange rate is not configured.'], 503);
        }
        return response()->json(['success' => true, 'data' => [
            'from' => 'USD', 'to' => 'AED',
            'rate' => bcdiv((string) $aed->exchange_rate, (string) $usd->exchange_rate, 6),
            'source' => 'platform_configuration',
            'updated_at' => collect([$usd->updated_at, $aed->updated_at])->filter()->sort()->last(),
        ]]);
    }

    public function completedOrders(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->user_type, ['customer', 'seller'], true), 403);
        $count = app(CompletedOrders::class)->count($user);
        return response()->json(['success' => true, 'data' => ['count' => $count, 'role' => $user->user_type]]);
    }

    public function reputation(Request $request)
    {
        return response()->json(['success' => true, 'data' => [
            'score' => (int) $request->user()->reputation_score, 'max_score' => 100,
        ]]);
    }
}
