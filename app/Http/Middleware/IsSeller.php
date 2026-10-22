<?php

namespace App\Http\Middleware;

use Closure;
use Auth;

class IsSeller
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!Auth::check()) {
            if ($request->routeIs('seller.products.destroy')) {
                // Resume at the list after login, never replay a deletion URL.
                $request->session()->put('link', route('seller.products'));
            }
            return redirect(\App\Services\PortalContext::loginUrl());
        }

        if (Auth::check() && Auth::user()->user_type == 'seller'  && !Auth::user()->banned) {
            return $next($request);
        }
        else{
            abort(404);
        }
    }
}
