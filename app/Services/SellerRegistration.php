<?php

namespace App\Services;

use App\Models\SellerPackage;
use App\Models\SellerPackagePayment;
use App\Models\Shop;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SellerRegistration
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'account_name' => 'required|string|max:191',
            'name' => 'required|string|max:191',
            'address' => 'required|string|max:500',
            'certtype' => ['required', Rule::in(['idcard', 'passport', 'driving license', 'mobile phone bill', 'water/electric bill'])],
            'identity_card_front' => ['required', 'integer', Rule::exists('uploads', 'id')->where('user_id', 0)->where('type', 'image')],
            'identity_card_back' => ['nullable', 'integer', Rule::exists('uploads', 'id')->where('user_id', 0)->where('type', 'image')],
        ]);
        $package = app(AccountMembership::class)->sellerPackage();
        if (!$package) {
            throw ValidationException::withMessages(['name' => translate('Sorry! Something went wrong.')]);
        }

        return app(PhoneAuth::class)->register($request, function (User $user) use ($data, $package) {
            $uploads = array_values(array_unique(array_filter([$data['identity_card_front'], $data['identity_card_back'] ?? null])));
            $available = DB::table('uploads')->where('user_id', 0)->whereIn('id', $uploads)->lockForUpdate()->get();
            if ($available->count() !== count($uploads)) {
                throw ValidationException::withMessages(['identity_card_front' => translate('Choose File')]);
            }
            $user->name = $data['account_name'];
            $user->user_type = 'seller';
            $user->identity_card_front = $data['identity_card_front'];
            $user->identity_card_back = $data['identity_card_back'] ?? '';
            $user->certtype = $data['certtype'];
            $inviter = User::find(Cookie::get('invitation_code'));
            if ($inviter) { $user->pid = $inviter->id; }
            $user->save();

            $shop = new Shop;
            $shop->user_id = $user->id;
            $shop->name = $data['name'];
            $shop->address = $data['address'];
            $shop->phone = $user->phone;
            $shop->slug = preg_replace('/\s+/', '-', $data['name']).'-'.$user->id;
            $shop->seller_package_id = $package->id;
            $shop->product_upload_limit = $package->product_upload_limit;
            $shop->package_invalid_at = AccountMembership::PERMANENT_UNTIL;
            $shop->verification_status = 0;
            // Required legacy columns have no defaults in some installations.
            $shop->seller_spread_package_id = 0;
            $shop->product_spread_limit = 0;
            $shop->bzj_money = 0;
            $shop->usdt_address = '';
            $shop->views_up_time = 0;
            $shop->save();

            $payment = new SellerPackagePayment;
            $payment->user_id = $user->id;
            $payment->seller_package_id = $package->id;
            $payment->payment_method = 'free';
            $payment->payment_details = '';
            $payment->approval = 1;
            $payment->offline_payment = 0;
            $payment->save();

            // Claim only the documents submitted with this application.
            Upload::where('user_id', 0)->whereIn('id', $uploads)->update(['user_id' => $user->id]);
        });
    }
}
