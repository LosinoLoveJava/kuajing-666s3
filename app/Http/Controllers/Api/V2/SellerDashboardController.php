<?php

namespace App\Http\Controllers\Api\V2;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\SellerRewards;

/** Read-only data for the future seller homepage. No client-supplied user ID. */
class SellerDashboardController extends Controller
{
    private function seller(Request $request)
    {
        $seller = $request->user();
        abort_unless($seller, 403);
        app(SellerRewards::class)->assertSeller($seller);
        return $seller;
    }

    public function reputation(Request $request)
    {
        $seller = $this->seller($request);
        return response()->json(['success' => true, 'data' => [
            'score' => (int) $seller->reputation_score,
            'max_score' => 100,
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function reputationHistory(Request $request)
    {
        $seller = $this->seller($request);
        $data = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|between:1,50',
        ]);
        $logs = DB::table('reputation_logs')->where('user_id', $seller->id)
            ->orderByDesc('id')->paginate($data['per_page'] ?? 20);
        // Operator IDs and other users' personal information are never exposed.
        return response()->json(['success' => true, 'data' => $logs->map(function ($log) {
            return [
                'id' => (int) $log->id,
                'old_score' => (int) $log->old_score,
                'new_score' => (int) $log->new_score,
                'change' => (int) $log->new_score - (int) $log->old_score,
                'reason' => $log->reason,
                'created_at' => $log->created_at,
            ];
        })->all(), 'meta' => [
            'current_page' => $logs->currentPage(), 'per_page' => $logs->perPage(),
            'total' => $logs->total(), 'last_page' => $logs->lastPage(),
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function rewardProgress(Request $request)
    {
        return response()->json(['success' => true, 'data' => app(SellerRewards::class)
            ->progress($this->seller($request))])->header('Cache-Control', 'private, no-store');
    }

    public function claimReward(Request $request, $milestone)
    {
        return response()->json(['success' => true, 'data' => app(SellerRewards::class)
            ->claim($this->seller($request), $milestone)])->header('Cache-Control', 'private, no-store');
    }
}
