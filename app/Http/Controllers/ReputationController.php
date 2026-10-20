<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReputationController extends Controller
{
    private function authorizeAdmin(Request $request)
    {
        // Existing admin middleware also admits salesmen; score changes are administrator-only.
        abort_unless($request->user() && $request->user()->user_type === 'admin', 403);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $request->validate(['q' => 'nullable|string|max:100']);
        $users = User::whereIn('user_type', ['customer', 'seller'])
            ->when($request->filled('q'), function ($query) use ($request) {
                $query->where(function ($query) use ($request) {
                    $query->where('name', 'like', '%'.$request->q.'%')
                        ->orWhere('email', 'like', '%'.$request->q.'%')
                        ->orWhere('phone', 'like', '%'.$request->q.'%');
                });
            })->orderBy('id')->paginate(20)->appends($request->only('q'));
        $logs = DB::table('reputation_logs')->leftJoin('users as target', 'target.id', '=', 'reputation_logs.user_id')
            ->leftJoin('users as operator', 'operator.id', '=', 'reputation_logs.operator_id')
            ->select('reputation_logs.*', 'target.name as user_name', 'operator.name as operator_name')
            ->orderByDesc('reputation_logs.id')->paginate(20, ['*'], 'logs_page');
        return view('backend.users.reputation', compact('users', 'logs'));
    }

    public function update(Request $request, $id)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['score' => 'required|integer|between:0,100', 'reason' => 'required|string|max:500']);
        DB::transaction(function () use ($request, $id, $data) {
            $user = User::whereIn('user_type', ['customer', 'seller'])->lockForUpdate()->findOrFail($id);
            DB::table('reputation_logs')->insert([
                'user_id' => $user->id, 'operator_id' => $request->user()->id,
                'old_score' => $user->reputation_score, 'new_score' => $data['score'],
                'reason' => $data['reason'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            $user->reputation_score = $data['score'];
            $user->save();
        });
        return back()->with('status', translate('Reputation score updated.'));
    }
}
