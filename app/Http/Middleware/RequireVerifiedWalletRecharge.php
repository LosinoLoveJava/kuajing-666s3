<?php

namespace App\Http\Middleware;

use Closure;

class RequireVerifiedWalletRecharge
{
    public function handle($request, Closure $next)
    {
        // Stop unsupported app wallet payments before a gateway can collect funds.
        if ($request->input('payment_type') === 'wallet_payment' || $request->input('value_c') === 'wallet_payment') {
            return response()->json(['result' => false, 'message' => 'Please recharge from My Wallet.'], 422);
        }
        return $next($request);
    }
}
