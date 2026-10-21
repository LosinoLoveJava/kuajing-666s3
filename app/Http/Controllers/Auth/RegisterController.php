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
        $request->merge(['mode' => 'register', 'phone' => strpos($phone, '+') === 0 ? $phone : '+'.$request->country_code.$phone]);
        return app(\App\Http\Controllers\PhoneAccessController::class)->submit($request);
    }
}
