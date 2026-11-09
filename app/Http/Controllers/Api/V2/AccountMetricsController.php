<?php

namespace App\Http\Controllers\Api\V2;

use App\Services\ExchangeRate;
use App\Services\CompletedOrders;
use Illuminate\Http\Request;

class AccountMetricsController extends Controller
{
    public function exchangeRate(Request $request)
    {
        $request->validate(['from' => 'sometimes|in:USD', 'to' => 'sometimes|in:SGD']);
        try {
            return response()->json(['success' => true, 'data' => app(ExchangeRate::class)->quote()]);
        } catch (\RuntimeException $error) {
            return response()->json(['success' => false, 'message' => $error->getMessage()], 503);
        }
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
