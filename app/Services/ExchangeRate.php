<?php

namespace App\Services;

use App\Models\Currency;
use RuntimeException;

class ExchangeRate
{
    public function quote($to = 'TRY')
    {
        if ($to !== 'TRY') throw new RuntimeException('Unsupported exchange currency.');
        $currencies = Currency::whereIn('code', ['USD', $to])->get()->keyBy('code');
        $usd = $currencies->get('USD');
        $sgd = $currencies->get($to);
        foreach ([$usd, $sgd] as $currency) {
            if (!$currency || !preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', (string) $currency->exchange_rate)
                || bccomp((string) $currency->exchange_rate, '0', 12) <= 0) {
                throw new RuntimeException('USD/'.$to.' exchange rate is not configured.');
            }
        }
        $rate = bcdiv((string) $sgd->exchange_rate, (string) $usd->exchange_rate, 6);
        if (bccomp($rate, '0', 6) <= 0) {
            throw new RuntimeException('USD/'.$to.' exchange rate is not configured.');
        }
        return ['from' => 'USD', 'to' => $to, 'rate' => $rate,
            'source' => 'platform_configuration',
            'updated_at' => collect([$usd->updated_at, $sgd->updated_at])->filter()->sort()->last()];
    }

    public function rate($to = 'TRY')
    {
        return $this->quote($to)['rate'];
    }

    public function toUsd($amount, $currency)
    {
        return bcadd(bcdiv((string) $amount, $this->rate($currency), 12), '0.005', 2);
    }

    public function fromUsd($amount, $currency)
    {
        return bcadd(bcmul((string) $amount, $this->rate($currency), 12), '0.005', 2);
    }

    public function availableRates()
    {
        $rates = [];
        foreach (['TRY'] as $currency) {
            try { $rates[$currency] = $this->rate($currency); }
            catch (RuntimeException $error) { $rates[$currency] = null; }
        }
        return $rates;
    }
}
