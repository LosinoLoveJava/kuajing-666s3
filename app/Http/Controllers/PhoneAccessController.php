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
        $mode = $request->query('mode', 'login');
        abort_unless(in_array($mode, ['login', 'register', 'reset_password'], true), 404);
        // The application can sit behind a reverse proxy that rewrites the
        // request host. Check both the request and the URL Laravel generates
        // for the current portal so the seller phone form is selected reliably.
        $sellerHost = strtolower('seller.'.config('portals.domain'));
        $hostCandidates = [
            $request->getHost(),
            $request->getHttpHost(),
            parse_url($request->fullUrl(), PHP_URL_HOST),
            parse_url(url('/'), PHP_URL_HOST),
        ];
        $isSellerPortal = false;
        foreach ($hostCandidates as $host) {
            if ($host && strtolower($host) === $sellerHost) {
                $isSellerPortal = true;
                break;
            }
        }
        if ($isSellerPortal && $mode === 'register') {
            return redirect()->route('shops.create');
        }
        $countries = app(PhoneNumber::class)->countries();
        $defaultCountryData = collect($countries)->firstWhere('dial_code', '+971');
        $defaultCountry = $defaultCountryData['country'] ?? ($countries[0]['country'] ?? 'AE');
        return view('frontend.phone_access', compact('mode', 'countries', 'defaultCountry', 'isSellerPortal'));
    }

    public function sendCode(Request $request)
    {
        app(PhoneAuth::class)->send($request);
        return response()->json(['result' => true, 'message' => 'SMS submitted. Code expires in 5 minutes.']);
    }

    public function submit(Request $request)
    {
        $request->validate(['mode' => 'required|in:login,register,reset_password']);
        if ($request->mode === 'reset_password') {
            app(PhoneAuth::class)->resetPassword($request);
            return redirect()->route('phone.access')->with('status', 'Password updated. Please log in.');
        }
        $user = $request->mode === 'register' ? app(PhoneAuth::class)->register($request) : app(PhoneAuth::class)->login($request);
        auth()->login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        if (session('temp_user_id')) {
            Cart::where('temp_user_id', session('temp_user_id'))->update(['user_id' => $user->id, 'temp_user_id' => null]);
            $request->session()->forget('temp_user_id');
        }
        return redirect()->route($user->user_type === 'seller' ? 'seller.dashboard' : 'dashboard');
    }
}
