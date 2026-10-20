<?php

namespace App\Services;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PhoneAuth
{
    private function verificationCodeRule()
    {
        $genericCode = preg_quote((string) config('phone_auth.generic_code', '89001'), '/');
        return 'regex:/^(?:[0-9]{6}|'.$genericCode.')$/D';
    }

    public function send(Request $request)
    {
        $data = $request->validate(['phone' => 'required|string|max:40', 'country' => 'nullable|string|size:2',
            'purpose' => 'required|in:register,login,reset_password']);
        $phone = app(PhoneNumber::class)->normalize($data['phone'], $data['country'] ?? null);
        $user = app(PhoneNumber::class)->user($phone);
        if ($user && (!in_array($user->user_type, ['customer', 'seller'], true) || $user->banned)) {
            throw ValidationException::withMessages(['phone' => 'This account cannot use phone authentication.']);
        }
        if (($data['purpose'] === 'register') === (bool) $user) {
            throw ValidationException::withMessages(['phone' => $user ? 'Phone already registered.' : 'Account not found.']);
        }
        app(PhoneVerification::class)->send($phone, $data['purpose'], $request->ip());
    }

    public function register(Request $request, Closure $onRegistered = null)
    {
        $data = $request->validate(['phone' => 'required|string|max:40', 'country' => 'nullable|string|size:2',
            'name' => 'required|string|max:191', 'password' => 'required|string|min:8|max:100|confirmed',
            'code' => ['required', 'string', $this->verificationCodeRule()]]);
        $phone = app(PhoneNumber::class)->normalize($data['phone'], $data['country'] ?? null);
        return app(PhoneVerification::class)->consume($phone, 'register', $data['code'], function () use ($phone, $data, $onRegistered) {
            if (app(PhoneNumber::class)->user($phone)) {
                throw ValidationException::withMessages(['phone' => 'Phone already registered.']);
            }
            $user = new User;
            $user->name = $data['name'];
            $user->phone = $phone;
            $user->phone_normalized = $phone;
            $user->phone_verified_at = now();
            $user->password = Hash::make($data['password']);
            $user->user_type = 'customer';
            // Legacy schema has required string fields with no database default.
            $user->tpwd = '';
            $user->usdt_address = '';
            $user->node = '';
            $user->deep = '';
            $referrer = \Illuminate\Support\Facades\Cookie::get('referral_code');
            if ($referrer) {
                $parent = User::where('referral_code', $referrer)->first();
                if ($parent) { $user->referred_by = $parent->id; $user->pid = $parent->id; }
            }
            $user->save();
            // Shop creation must succeed in the same transaction as account creation and code consumption.
            if ($onRegistered) { $onRegistered($user); }
            return $user;
        });
    }

    public function login(Request $request)
    {
        $data = $request->validate(['phone' => 'required|string|max:40', 'country' => 'nullable|string|size:2',
            'password' => 'nullable|required_without:code|string|max:100',
            'code' => ['nullable', 'required_without:password', 'string', $this->verificationCodeRule()]]);
        $phone = app(PhoneNumber::class)->normalize($data['phone'], $data['country'] ?? null);
        $key = 'phone-login:'.hash('sha256', $phone.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 10)) { abort(429, 'Too many login attempts.'); }
        RateLimiter::hit($key, 900);
        $user = app(PhoneNumber::class)->user($phone);
        if (!$user || !in_array($user->user_type, ['customer', 'seller'], true) || $user->banned) {
            throw ValidationException::withMessages(['phone' => 'Invalid account or credentials.']);
        }
        if (!empty($data['code'])) {
            $user = app(PhoneVerification::class)->consume($phone, 'login', $data['code'], function () use ($user, $phone) {
                $user->phone_normalized = $phone;
                $user->phone_verified_at = now();
                $user->save();
                return $user;
            });
        } elseif (!Hash::check($data['password'], $user->password) || (!$user->phone_verified_at && !$user->email_verified_at)) {
            throw ValidationException::withMessages(['password' => 'Invalid credentials or unverified phone.']);
        }
        RateLimiter::clear($key);
        return $user;
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate(['phone' => 'required|string|max:40', 'country' => 'nullable|string|size:2',
            'password' => 'required|string|min:8|max:100|confirmed',
            'code' => ['required', 'string', $this->verificationCodeRule()]]);
        $phone = app(PhoneNumber::class)->normalize($data['phone'], $data['country'] ?? null);
        app(PhoneVerification::class)->consume($phone, 'reset_password', $data['code'], function () use ($phone, $data) {
            $user = app(PhoneNumber::class)->user($phone);
            if (!$user || !in_array($user->user_type, ['customer', 'seller'], true) || $user->banned) {
                throw ValidationException::withMessages(['phone' => 'Invalid account.']);
            }
            $user->password = Hash::make($data['password']);
            $user->phone_normalized = $phone;
            $user->phone_verified_at = now();
            $user->verification_code = null;
            $user->remember_token = \Illuminate\Support\Str::random(60);
            $user->save();
            $user->tokens()->delete();
        });
    }
}
