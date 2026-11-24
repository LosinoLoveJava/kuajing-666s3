<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showRegistrationForm()
    {
        // Keep referral handling in the existing public registration entry.
        return app(\App\Http\Controllers\HomeController::class)->registration(request());
    }

    public function register(Request $request)
    {
        $request->validate(['email' => 'prohibited']);
        $phone = (string) $request->phone;
        $request->validate(['country_code' => 'nullable|in:60']);
        $country = $request->country ?: ((string) $request->country_code === '60' ? 'MY' : null);
        $request->merge(['mode' => 'register', 'phone' => app(\App\Services\PhoneNumber::class)->normalize($phone, $country)]);
        return app(\App\Http\Controllers\PhoneAccessController::class)->submit($request);
    }
}
