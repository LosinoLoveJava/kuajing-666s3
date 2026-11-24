<?php

namespace App\Services;

use App\Models\Currency;
use RuntimeException;

class ExchangeRate
{
    public function quote($to = 'MYR')
    {
        if ($to !== 'MYR') throw new RuntimeException('Unsupported exchange currency.');
        $currencies = Currency::whereIn('code', ['USD', $to])->get()->keyBy('code');
        $usd = $currencies->get('USD');
        $local = $currencies->get($to);
        foreach ([$usd, $local] as $currency) {
            if (!$currency || !preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', (string) $currency->exchange_rate)
                || bccomp((string) $currency->exchange_rate, '0', 12) <= 0) {
                throw new RuntimeException('USD/'.$to.' exchange rate is not configured.');
            }
        }
        $rate = bcdiv((string) $local->exchange_rate, (string) $usd->exchange_rate, 6);
        if (bccomp($rate, '0', 6) <= 0) {
            throw new RuntimeException('USD/'.$to.' exchange rate is not configured.');
        }
        return ['from' => 'USD', 'to' => $to, 'rate' => $rate,
            'source' => 'platform_configuration',
            'updated_at' => collect([$usd->updated_at, $local->updated_at])->filter()->sort()->last()];
    }

    public function rate($to = 'MYR')
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
        foreach (['MYR'] as $currency) {
            try { $rates[$currency] = $this->rate($currency); }
            catch (RuntimeException $error) { $rates[$currency] = null; }
        }
        return $rates;
    }
}
