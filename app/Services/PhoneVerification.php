<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class PhoneVerification
{
    public function send($phone, $purpose, $ip)
    {
        if (!in_array($purpose, ['register', 'login', 'reset_password', 'verify_phone'], true)) {
            throw ValidationException::withMessages(['purpose' => 'Invalid verification purpose.']);
        }
        foreach (['sms-phone:'.hash('sha256', $phone) => 10, 'sms-ip:'.hash('sha256', $ip) => 30] as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                abort(429, 'Too many SMS requests. Please try again later.');
            }
            RateLimiter::hit($key, 3600);
        }
        DB::table('phone_verifications')->insertOrIgnore(['phone' => $phone, 'created_at' => now(), 'updated_at' => now()]);
        $outcome = DB::transaction(function () use ($phone, $purpose) {
            $row = DB::table('phone_verifications')->where('phone', $phone)->lockForUpdate()->first();
            if ($row->last_sent_at && now()->diffInSeconds(\Carbon\Carbon::parse($row->last_sent_at), false) > -60) {
                return 'cooldown';
            }
            $code = (string) random_int(100000, 999999);
            // Count failed/uncertain submissions towards cooldown as well; never auto-resend on a timeout.
            DB::table('phone_verifications')->where('id', $row->id)->update(['last_sent_at' => now(), 'updated_at' => now()]);
            try {
                $uid = app(ChuanxSms::class)->send($phone, $code);
            } catch (\Exception $error) {
                return 'failed';
            }
            DB::table('phone_verifications')->where('id', $row->id)->update([
                'purpose' => $purpose, 'code_hash' => Hash::make($code), 'attempts' => 0,
                'expires_at' => now()->addMinutes(5), 'provider_uid' => $uid, 'updated_at' => now(),
            ]);
            return 'sent';
        });
        if ($outcome === 'cooldown') { abort(429, 'Please wait 60 seconds before requesting another code.'); }
        if ($outcome !== 'sent') { abort(503, 'SMS is temporarily unavailable. Please try again later.'); }
    }

    public function consume($phone, $purpose, $code, Closure $action)
    {
        $outcome = DB::transaction(function () use ($phone, $purpose, $code, $action) {
            $row = DB::table('phone_verifications')->where('phone', $phone)->lockForUpdate()->first();
            if (!$row || !$row->code_hash || $row->purpose !== $purpose || !$row->expires_at
                || now()->greaterThanOrEqualTo(\Carbon\Carbon::parse($row->expires_at)) || $row->attempts >= 5) {
                return [false, null];
            }
            if (!is_string($code) || !Hash::check($code, $row->code_hash)) {
                DB::table('phone_verifications')->where('id', $row->id)->increment('attempts');
                return [false, null];
            }
            $result = $action();
            DB::table('phone_verifications')->where('id', $row->id)->update(['code_hash' => null, 'expires_at' => null, 'updated_at' => now()]);
            return [true, $result];
        });
        // Throw outside the transaction so failed attempts are committed.
        if (!$outcome[0]) {
            throw ValidationException::withMessages(['code' => 'Invalid or expired verification code.']);
        }
        return $outcome[1];
    }
}
