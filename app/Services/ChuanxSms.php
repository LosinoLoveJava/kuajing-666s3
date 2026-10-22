<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ChuanxSms
{
    public function send($phone, $code)
    {
        foreach (['appkey', 'appcode', 'appsecret'] as $key) {
            if (!config('chuanx.'.$key)) {
                throw new RuntimeException('SMS provider is not configured.');
            }
        }
        $digits = ltrim($phone, '+');
        $response = Http::withOptions(['connect_timeout' => 3])->timeout(8)->get(rtrim(config('chuanx.base_url'), '/').'/sms/batch/v2', [
            'appkey' => config('chuanx.appkey'), 'appcode' => config('chuanx.appcode'),
            'appsecret' => config('chuanx.appsecret'), 'phone' => $digits,
            'uid' => bin2hex(random_bytes(16)),
            'msg' => '[PDD] Your verification code is '.$code.'.',
        ]);
        $body = $response->json();
        if (!$response->successful() || !is_array($body) || ($body['code'] ?? null) !== '00000') {
            throw new RuntimeException('SMS submission failed.');
        }
        foreach (($body['result'] ?? []) as $result) {
            if (($result['phone'] ?? null) === $digits && ($result['status'] ?? null) === '00000') {
                $uid = $body['uid'] ?? '';
                if (is_string($uid) && $uid !== '' && strlen($uid) <= 32) { return $uid; }
            }
        }
        throw new RuntimeException('SMS submission failed.');
    }
}
