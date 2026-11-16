<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddTurkeyMarket extends Migration
{
    public function up()
    {
        DB::transaction(function () {
            $usd = DB::table('currencies')->where('code', 'USD')->first();
            if (!$usd || !is_numeric($usd->exchange_rate) || $usd->exchange_rate <= 0) {
                throw new RuntimeException('A positive USD base rate is required.');
            }
            $values = ['exchange_rate' => bcmul((string) $usd->exchange_rate, '50.5', 5), 'status' => 1, 'updated_at' => now()];
            if (DB::table('currencies')->where('code', 'TRY')->exists()) {
                DB::table('currencies')->where('code', 'TRY')->update($values);
            } else {
                DB::table('currencies')->insert($values + ['code' => 'TRY', 'name' => 'Turkish Lira', 'symbol' => '₺', 'created_at' => now()]);
            }
            if (DB::table('countries')->whereRaw('UPPER(code) = ?', ['TR'])->exists()) {
                DB::table('countries')->whereRaw('UPPER(code) = ?', ['TR'])->update(['status' => 1]);
            } else {
                DB::table('countries')->insert(['code' => 'TR', 'name' => 'Turkey', 'status' => 1]);
            }
        });
    }

    public function down()
    {
        // Retain currency configuration referenced by financial records.
    }
}
