<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AddTurkishLanguageAndCatalog extends Migration
{
    public function up()
    {
        $pack = json_decode(file_get_contents(database_path('data/turkish_market.json')), true, 512, JSON_THROW_ON_ERROR);
        // Translated names can be longer than the original English column limits.
        if (DB::connection()->getDriverName() === 'mysql') {
            $categoryName = DB::selectOne("SHOW COLUMNS FROM category_translations LIKE 'name'");
            $productName = DB::selectOne("SHOW COLUMNS FROM product_translations LIKE 'name'");
            if ($categoryName->Type !== 'varchar(255)') DB::statement('ALTER TABLE category_translations MODIFY name VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL');
            if ($productName->Type !== 'varchar(512)') DB::statement('ALTER TABLE product_translations MODIFY name VARCHAR(512) COLLATE utf8_unicode_ci DEFAULT NULL');
        }
        DB::transaction(function () use ($pack) {
            $language = DB::table('languages')->where('code', 'tr')->first();
            if ($language) {
                DB::table('languages')->where('id', $language->id)->update(['status' => 1, 'rtl' => 0, 'app_lang_code' => 'tr', 'updated_at' => now()]);
            } else {
                DB::table('languages')->insert(['code' => 'tr', 'name' => 'Türkçe', 'app_lang_code' => 'tr', 'rtl' => 0, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
            $existing = DB::table('translations')->where('lang', 'tr')->pluck('lang_value', 'lang_key')->all();
            $rows = [];
            foreach ($pack['interface'] as $key => $value) {
                if (!isset($existing[$key]) || trim($existing[$key]) === '' || (isset($pack['legacy_interface'][$key]) && $existing[$key] === $pack['legacy_interface'][$key])) {
                    if (array_key_exists($key, $existing)) {
                        DB::table('translations')->where('lang', 'tr')->where('lang_key', $key)->update(['lang_value' => $value, 'updated_at' => now()]);
                    } else {
                        $rows[] = ['lang' => 'tr', 'lang_key' => $key, 'lang_value' => $value, 'created_at' => now(), 'updated_at' => now()];
                    }
                }
            }
            foreach (array_chunk($rows, 100) as $chunk) DB::table('translations')->insert($chunk);
            foreach (['categories' => ['category_translations', 'category_id'], 'products' => ['product_translations', 'product_id']] as $table => $translation) {
                list($target, $foreignKey) = $translation;
                $caseInsensitiveNames = array_change_key_case($pack[$table], CASE_LOWER);
                DB::table($table)->select('id', 'name')->orderBy('id')->chunkById(100, function ($items) use ($pack, $table, $target, $foreignKey, $caseInsensitiveNames) {
                    $existing = DB::table($target)->where('lang', 'tr')->whereIn($foreignKey, $items->pluck('id'))->pluck('name', $foreignKey)->all();
                    $rows = [];
                    foreach ($items as $item) {
                        $name = $pack[$table][$item->name] ?? ($caseInsensitiveNames[strtolower($item->name)] ?? null);
                        if (!$name || (isset($existing[$item->id]) && trim($existing[$item->id]) !== '')) continue;
                        if (array_key_exists($item->id, $existing)) {
                            DB::table($target)->where('lang', 'tr')->where($foreignKey, $item->id)->update(['name' => $name, 'updated_at' => now()]);
                        } else {
                            $rows[] = [$foreignKey => $item->id, 'lang' => 'tr', 'name' => $name, 'created_at' => now(), 'updated_at' => now()];
                        }
                    }
                    if ($rows) DB::table($target)->insert($rows);
                });
            }
            $labels = json_decode(file_get_contents(database_path('data/turkey_market_labels.json')), true, 512, JSON_THROW_ON_ERROR);
            foreach ($labels as $locale => $messages) {
                foreach ($messages as $source => $value) {
                    $key = preg_replace('/[^A-Za-z0-9\_]/', '', str_replace(' ', '_', strtolower($source)));
                    $label = DB::table('translations')->where('lang', $locale)->where('lang_key', $key)->first();
                    if (!$label) {
                        DB::table('translations')->insert(['lang' => $locale, 'lang_key' => $key, 'lang_value' => $value, 'created_at' => now(), 'updated_at' => now()]);
                    } elseif ($locale === 'tr' && $label->lang_value === ($pack['interface'][$key] ?? null)) {
                        DB::table('translations')->where('id', $label->id)->update(['lang_value' => $value, 'updated_at' => now()]);
                    }
                }
            }
        });
        foreach (['tr', 'en', 'cn', 'hk'] as $locale) Cache::forget('translations-'.$locale);
        Cache::forget('app.languages');
        Cache::forget('all_categories');
        Cache::forget('featured_categories');
    }

    public function down()
    {
        // Keep catalog copy and administrator edits.
    }
}
