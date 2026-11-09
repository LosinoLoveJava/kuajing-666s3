<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ConfigureSingaporeMarket extends Migration
{
    public function up()
    {
        DB::transaction(function () {
            $usd = DB::table('currencies')->where('code', 'USD')->first();
            if (!$usd || !is_numeric($usd->exchange_rate) || $usd->exchange_rate <= 0) {
                throw new RuntimeException('A positive USD base rate is required.');
            }
            $values = ['exchange_rate' => bcmul((string) $usd->exchange_rate, '1.28', 5), 'status' => 1, 'updated_at' => now()];
            if (DB::table('currencies')->where('code', 'SGD')->exists()) {
                DB::table('currencies')->where('code', 'SGD')->update($values);
            } else {
                DB::table('currencies')->insert($values + ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$', 'created_at' => now()]);
            }
            if (DB::table('countries')->whereRaw('UPPER(code) = ?', ['SG'])->exists()) {
                DB::table('countries')->whereRaw('UPPER(code) = ?', ['SG'])->update(['status' => 1]);
            } else {
                DB::table('countries')->insert(['code' => 'SG', 'name' => 'Singapore', 'status' => 1]);
            }
        });
    }

    public function down()
    {
        // Keep currency/country configuration used by financial history.
    }
}
