<?php

namespace App\Http\Controllers\Api\V2;

use App\Services\PhoneAuth;
use App\Services\PhoneNumber;
use Illuminate\Http\Request;

class PhoneAuthController extends AuthController
{
    public function countries()
    {
        return response()->json(['success' => true, 'data' => app(PhoneNumber::class)->countries()]);
    }

    public function sendCode(Request $request)
    {
        app(PhoneAuth::class)->send($request);
        return response()->json(['result' => true, 'message' => 'SMS submitted.', 'expires_in' => 300, 'retry_after' => 60]);
    }

    public function registerPhone(Request $request)
    {
        return $this->loginSuccess(app(PhoneAuth::class)->register($request));
    }

    public function loginPhone(Request $request)
    {
        return $this->loginSuccess(app(PhoneAuth::class)->login($request));
    }

    public function resetPhonePassword(Request $request)
    {
        app(PhoneAuth::class)->resetPassword($request);
        return response()->json(['result' => true, 'message' => 'Password updated. Please log in.']);
    }
}
