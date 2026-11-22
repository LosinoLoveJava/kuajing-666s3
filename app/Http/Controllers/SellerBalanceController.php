<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Http\Request;

class SellerBalanceController extends Controller
{
    public function show(Request $request, $id)
    {
        abort_unless($request->user() && in_array($request->user()->user_type, ['admin', 'staff'], true)
            && !$request->user()->banned, 403);
        $seller = User::where('user_type', 'seller')->findOrFail($id);
        $deductions = Wallet::where('user_id', $seller->id)->where('payment_method', WalletService::ADMIN_DEBIT)
            ->with('operator:id,name')->orderByDesc('id')->paginate(15);
        return view('backend.sellers.balance_deduction', compact('seller', 'deductions'));
    }

    public function store(Request $request, $id)
    {
        app(WalletService::class)->debitSeller($request, $id);
        return redirect()->route('sellers.balance_deduction', $id)->with('status', __('seller_balance.success'));
    }
}
