<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Services\PhoneAuth;
use App\Services\PhoneNumber;
use Illuminate\Http\Request;

class PhoneAccessController extends Controller
{
    public function show(Request $request)
    {
        $mode = $request->route('mode') ?? $request->query('mode', 'login');
        abort_unless(in_array($mode, ['login', 'register', 'reset_password'], true), 404);
        $isSellerPortal = ($request->attributes->get('portal') ?? \App\Services\PortalContext::resolve($request)) === 'seller';
        if ($isSellerPortal && $mode === 'register') {
            return redirect()->route('shops.create', ['source' => 'seller']);
        }
        $countries = app(PhoneNumber::class)->countries();
        $defaultCountryData = collect($countries)->firstWhere('dial_code', '+971');
        $defaultCountry = $defaultCountryData['country'] ?? ($countries[0]['country'] ?? 'AE');
        return view('frontend.phone_access', compact('mode', 'countries', 'defaultCountry', 'isSellerPortal'));
    }

    public function sendCode(Request $request)
    {
        $request->validate(['purpose' => 'required|in:register,reset_password']);
        app(PhoneAuth::class)->send($request);
        return response()->json(['result' => true, 'message' => 'SMS submitted. Code expires in 5 minutes.']);
    }

    public function submit(Request $request)
    {
        $isSellerPortal = \App\Services\PortalContext::current() === 'seller';
        $request->validate(['mode' => $isSellerPortal ? 'required|in:login,reset_password' : 'required|in:login,register,reset_password']);
        if ($request->mode === 'login') {
            $request->validate(['password' => 'required|string|max:100', 'code' => 'prohibited']);
        }
        if ($request->mode === 'reset_password') {
            app(PhoneAuth::class)->resetPassword($request);
            return redirect()->route($isSellerPortal ? 'login' : 'phone.access')->with('status', 'Password updated. Please log in.');
        }
        $user = $request->mode === 'register' ? app(PhoneAuth::class)->register($request) : app(PhoneAuth::class)->login($request);
        app(\App\Services\PortalLogin::class)->login($user, $request, $request->boolean('remember'));
        if (session('temp_user_id')) {
            Cart::where('temp_user_id', session('temp_user_id'))->update(['user_id' => $user->id, 'temp_user_id' => null]);
            $request->session()->forget('temp_user_id');
        }
        return redirect(\App\Services\PortalContext::dashboardUrl($user));
    }
}
