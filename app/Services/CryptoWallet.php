<?php

namespace App\Services;

use App\Models\BusinessSetting;
use Illuminate\Validation\ValidationException;

class CryptoWallet
{
    public const SETTING = 'wallet_crypto_assets';

    public static function catalog()
    {
        return [
            'USDT_TRC20' => ['label' => 'USDT TRC20', 'coin' => 'USDT', 'network' => 'TRON (TRC20)', 'logo' => 'usdt'],
            'USDT_ERC20' => ['label' => 'USDT ERC20', 'coin' => 'USDT', 'network' => 'Ethereum (ERC20)', 'logo' => 'usdt'],
            'BTC' => ['label' => 'Bitcoin (BTC)', 'coin' => 'BTC', 'network' => 'Bitcoin', 'logo' => 'btc'],
            'TRX' => ['label' => 'TRX', 'coin' => 'TRX', 'network' => 'TRON', 'logo' => 'trx'],
        ];
    }

    public static function assets()
    {
        $settings = json_decode(BusinessSetting::where('type', self::SETTING)->value('value') ?? '', true) ?: [];
        $assets = [];
        foreach (self::catalog() as $code => $asset) {
            $config = $settings[$code] ?? [];
            $price = (string) ($config['usd_price'] ?? '');
            $validPrice = preg_match('/^[0-9]{1,10}(\.[0-9]{1,8})?$/D', $price) && bccomp($price, '0', 8) > 0;
            $assets[$code] = $asset + ['address' => (string) ($config['address'] ?? ''),
                'usd_price' => $validPrice ? $price : null,
                'enabled' => !empty($config['enabled']) && $validPrice,
                'deposit_enabled' => !empty($config['enabled']) && $validPrice && self::validAddress($code, $config['address'] ?? '')];
        }
        return $assets;
    }

    public static function asset($code, $deposit = false)
    {
        $asset = self::assets()[$code] ?? null;
        if (!$asset || !($deposit ? $asset['deposit_enabled'] : $asset['enabled'])) {
            throw ValidationException::withMessages(['crypto_asset' => __('crypto_wallet.unavailable')]);
        }
        return $asset;
    }

    public static function validAddress($code, $address)
    {
        if (!is_string($address)) return false;
        if (in_array($code, ['USDT_TRC20', 'TRX'], true)) return (bool) preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/D', $address);
        if ($code === 'USDT_ERC20') return (bool) preg_match('/^0x[0-9a-fA-F]{40}$/D', $address);
        if ($code === 'BTC') return (bool) preg_match('/^(?:[13][1-9A-HJ-NP-Za-km-z]{25,34}|bc1[ac-hj-np-z02-9]{11,87}|BC1[AC-HJ-NP-Z02-9]{11,87})$/D', $address);
        return false;
    }

    public static function toUsd($quantity, $price)
    {
        return bcadd(bcmul((string) $quantity, $price, 16), '0.005', 2);
    }

    public static function fromUsd($amount, $price)
    {
        // Truncate to eight decimals so the quoted payout never exceeds the reserved USD.
        return bcdiv((string) $amount, $price, 8);
    }
}
