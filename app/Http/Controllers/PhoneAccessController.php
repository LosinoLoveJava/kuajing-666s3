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
        $countries = app(PhoneNumber::class)->countries();
        return view('frontend.phone_access', compact('mode', 'countries'));
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
