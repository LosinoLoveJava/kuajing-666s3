<?php

namespace App\Services;

use App\Models\Currency;
use RuntimeException;

class ExchangeRate
{
    public function quote()
    {
        $currencies = Currency::whereIn('code', ['USD', 'SGD'])->get()->keyBy('code');
        $usd = $currencies->get('USD');
        $sgd = $currencies->get('SGD');
        foreach ([$usd, $sgd] as $currency) {
            if (!$currency || !preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', (string) $currency->exchange_rate)
                || bccomp((string) $currency->exchange_rate, '0', 12) <= 0) {
                throw new RuntimeException('USD/SGD exchange rate is not configured.');
            }
        }
        $rate = bcdiv((string) $sgd->exchange_rate, (string) $usd->exchange_rate, 6);
        if (bccomp($rate, '0', 6) <= 0) {
            throw new RuntimeException('USD/SGD exchange rate is not configured.');
        }
        return ['from' => 'USD', 'to' => 'SGD', 'rate' => $rate,
            'source' => 'platform_configuration',
            'updated_at' => collect([$usd->updated_at, $sgd->updated_at])->filter()->sort()->last()];
    }

    public function rate()
    {
        return $this->quote()['rate'];
    }

    public function sgdToUsd($amount)
    {
        return bcadd(bcdiv((string) $amount, $this->rate(), 12), '0.005', 2);
    }
}
