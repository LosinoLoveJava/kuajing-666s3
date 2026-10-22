<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AddSellerRechargeTranslations extends Migration
{
    public function up()
    {
        $translations = json_decode(file_get_contents(database_path('data/seller_recharge_translations.json')), true, 512, JSON_THROW_ON_ERROR);
        DB::transaction(function () use ($translations) {
            foreach ($translations as $locale => $messages) {
                foreach ($messages as $source => $value) {
                    $key = preg_replace('/[^A-Za-z0-9\_]/', '', str_replace(' ', '_', strtolower($source)));
                    if (!DB::table('translations')->where('lang', $locale)->where('lang_key', $key)->exists()) {
                        DB::table('translations')->insert([
                            'lang' => $locale, 'lang_key' => $key, 'lang_value' => $value,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }
        });
        foreach (array_keys($translations) as $locale) {
            Cache::forget('translations-'.$locale);
        }
    }

    public function down()
    {
        // Preserve administrators' customized translations.
    }
}
