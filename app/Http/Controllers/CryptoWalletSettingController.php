<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Services\CryptoWallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CryptoWalletSettingController extends Controller
{
    private function authorizeAdmin(Request $request)
    {
        abort_unless($request->user() && $request->user()->user_type === 'admin', 403);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        return view('manual_payment_methods.crypto', ['assets' => CryptoWallet::assets()]);
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin($request);
        $rules = ['assets' => 'required|array'];
        foreach (CryptoWallet::catalog() as $code => $asset) {
            $rules['assets.'.$code] = 'required|array';
            $rules['assets.'.$code.'.enabled'] = 'nullable|boolean';
            $rules['assets.'.$code.'.address'] = 'nullable|string|max:128';
            $rules['assets.'.$code.'.usd_price'] = ['nullable', 'regex:/^[0-9]{1,10}(\.[0-9]{1,8})?$/D'];
        }
        $data = $request->validate($rules);
        $settings = [];
        foreach (CryptoWallet::catalog() as $code => $asset) {
            $item = $data['assets'][$code];
            $address = trim($item['address'] ?? '');
            $price = $item['usd_price'] ?? '';
            $enabled = !empty($item['enabled']);
            if (($address !== '' && !CryptoWallet::validAddress($code, $address)) || ($enabled && $address === '')) {
                throw ValidationException::withMessages(['assets.'.$code.'.address' => __('crypto_wallet.invalid_address')]);
            }
            if (($price !== '' && bccomp($price, '0', 8) <= 0) || ($enabled && $price === '')) {
                throw ValidationException::withMessages(['assets.'.$code.'.usd_price' => __('crypto_wallet.invalid_price')]);
            }
            $settings[$code] = ['enabled' => $enabled, 'address' => $address, 'usd_price' => $price];
        }
        DB::transaction(function () use ($settings) {
            DB::table('business_settings')->updateOrInsert(['type' => CryptoWallet::SETTING], ['value' => json_encode($settings)]);
        });
        return redirect()->route('crypto_wallet.settings')->with('crypto_saved', true);
    }
}
