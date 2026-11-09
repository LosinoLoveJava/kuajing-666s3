<?php

namespace App\Services;

use App\Models\Currency;

class ExchangeRate
{
    public function quote()
    {
        $currencies = Currency::whereIn('code', ['USD', 'SGD'])->get()->keyBy('code');
        $usd = $currencies->get('USD');
        $sgd = $currencies->get('SGD');
        abort_unless($usd && $sgd && is_numeric($usd->exchange_rate) && is_numeric($sgd->exchange_rate)
            && $usd->exchange_rate > 0 && $sgd->exchange_rate > 0, 503, 'USD/SGD exchange rate is not configured.');
        $rate = bcdiv((string) $sgd->exchange_rate, (string) $usd->exchange_rate, 6);
        abort_unless(bccomp($rate, '0', 6) > 0, 503, 'USD/SGD exchange rate is not configured.');
        return [
            'from' => 'USD', 'to' => 'SGD', 'rate' => $rate,
            'source' => 'platform_configuration',
            'updated_at' => collect([$usd->updated_at, $sgd->updated_at])->filter()->sort()->last(),
        ];
    }
}
