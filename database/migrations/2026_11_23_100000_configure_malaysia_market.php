<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ConfigureMalaysiaMarket extends Migration
{
    public function up()
    {
        DB::transaction(function () {
            $usd = DB::table('currencies')->where('code', 'USD')->first();
            if (!$usd || !is_numeric($usd->exchange_rate) || $usd->exchange_rate <= 0) {
                throw new RuntimeException('A positive USD base rate is required.');
            }
            $values = ['exchange_rate' => bcmul((string) $usd->exchange_rate, '4.17', 5),
                'name' => 'Malaysian Ringgit', 'symbol' => 'RM', 'status' => 1, 'updated_at' => now()];
            if (DB::table('currencies')->where('code', 'MYR')->exists()) {
                DB::table('currencies')->where('code', 'MYR')->update($values);
            } else {
                DB::table('currencies')->insert($values + ['code' => 'MYR', 'created_at' => now()]);
            }
            if (DB::table('countries')->whereRaw('UPPER(code) = ?', ['MY'])->exists()) {
                DB::table('countries')->whereRaw('UPPER(code) = ?', ['MY'])->update(['status' => 1]);
            } else {
                DB::table('countries')->insert(['code' => 'MY', 'name' => 'Malaysia', 'status' => 1]);
            }
            $labels = [
                'en' => ['Malaysia' => 'Malaysia', 'Malaysian Ringgit' => 'Malaysian Ringgit',
                    'Account holder' => 'Account holder', 'Bank Name' => 'Bank Name', 'Bank Account Number' => 'Bank Account Number'],
                'cn' => ['Malaysia' => '马来西亚', 'Malaysian Ringgit' => '马来西亚林吉特',
                    'Account holder' => '持户人姓名', 'Bank Name' => '银行名称', 'Bank Account Number' => '银行账户号码'],
                'hk' => ['Malaysia' => '馬來西亞', 'Malaysian Ringgit' => '馬來西亞令吉',
                    'Account holder' => '持戶人姓名', 'Bank Name' => '銀行名稱', 'Bank Account Number' => '銀行帳戶號碼'],
                'tr' => ['Malaysia' => 'Malezya', 'Malaysian Ringgit' => 'Malezya Ringgiti',
                    'Account holder' => 'Hesap sahibinin adı', 'Bank Name' => 'Banka adı', 'Bank Account Number' => 'Banka hesap numarası'],
            ];
            foreach ($labels as $locale => $messages) {
                foreach ($messages as $source => $value) {
                    $key = preg_replace('/[^A-Za-z0-9\_]/', '', str_replace(' ', '_', strtolower($source)));
                    $query = DB::table('translations')->where('lang', $locale)->where('lang_key', $key);
                    $existing = $query->first();
                    if (!$existing) {
                        DB::table('translations')->insert(['lang' => $locale, 'lang_key' => $key,
                            'lang_value' => $value, 'created_at' => now(), 'updated_at' => now()]);
                    } elseif (trim($existing->lang_value) === '' || $existing->lang_value === $source) {
                        $query->update(['lang_value' => $value, 'updated_at' => now()]);
                    }
                }
            }
        });
        foreach (['en', 'cn', 'hk', 'tr'] as $locale) Cache::forget('translations-'.$locale);
    }

    public function down()
    {
        // Keep configuration used by saved financial records.
    }
}
