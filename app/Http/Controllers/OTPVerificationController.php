<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Auth\Events\PasswordReset;
use Auth;
use Nexmo;
use App\Models\OtpConfiguration;
use App\Models\User;
use App\Utility\SmsUtility;
use Twilio\Rest\Client;
use Hash;

class OTPVerificationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function verification(Request $request){
        if (Auth::check() && !Auth::user()->hasVerifiedEmail()) {
            return view('otp_systems.frontend.user_verification');
        }
        else {
            flash('You have already verified your number')->warning();
            return redirect()->route('home');
        }
    }


    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function confirmUserPhone($user, $code)
    {
        abort_unless($user && in_array($user->user_type, ['customer', 'seller'], true) && !$user->banned, 403);
        $phone = app(\App\Services\PhoneNumber::class)->normalize($user->phone);
        app(\App\Services\PhoneVerification::class)->consume($phone, 'verify_phone', $code, function () use ($user, $phone) {
            $user->phone_verified_at = now();
            $user->phone_normalized = $phone;
            $user->verification_code = null;
            $user->save();
        });
    }

    public function verify_phone(Request $request)
    {
        $this->confirmUserPhone(Auth::user(), $request->input('code', $request->verification_code));
        flash('Your phone number has been verified successfully')->success();
        return redirect()->route('home');
    }

    public function resend_verificcation_code(Request $request)
    {
        abort_unless(Auth::check(), 401);
        $this->send_code(Auth::user());
        return back();
    }

    public function reset_password_with_code(Request $request)
    {
        app(\App\Services\PhoneAuth::class)->resetPassword($request);
        return redirect()->route('phone.access')->with('status', 'Password updated. Please log in.');
    }

    public function show_reset_password_form()
    {
        return redirect()->route('phone.access', ['mode' => 'reset_password']);
    }

    public function send_code($user)
    {
        abort_unless($user && in_array($user->user_type, ['customer', 'seller'], true) && !$user->banned, 403);
        $phone = app(\App\Services\PhoneNumber::class)->normalize($user->phone);
        app(\App\Services\PhoneVerification::class)->send($phone, 'verify_phone', request()->ip());
    }

    public function send_order_code($order){
        $phone = json_decode($order->shipping_address)->phone;
        if($phone != null){
            SmsUtility::order_placement($phone, $order);
        }
    }

    /**
     * @param  Order $order
     * @return void
     */
    public function send_delivery_status($order){
        $phone = json_decode($order->shipping_address)->phone;
        if($phone != null){
            SmsUtility::delivery_status_change($phone, $order);
        }
    }

    /**
     * @param  Order $order
     * @return void
     */
    public function send_payment_status($order){
        $phone = json_decode($order->shipping_address)->phone;
        if($phone != null){
            SmsUtility::payment_status_change($phone, $order);
        }
    }
}
