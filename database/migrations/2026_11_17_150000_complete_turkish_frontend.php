<?php

use App\Support\TurkishCopy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CompleteTurkishFrontend extends Migration
{
    public function up()
    {
        $pack = TurkishCopy::pack();
        $legacy = json_decode(file_get_contents(database_path('data/turkish_market.json')), true, 512, JSON_THROW_ON_ERROR);
        $path = database_path('data/turkish_product_texts.json.gz');
        $descriptionTexts = json_decode(gzdecode(file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($pack, $legacy, $descriptionTexts) {
            $existing = DB::table('translations')->where('lang', 'tr')->pluck('lang_value', 'lang_key')->all();
            $rows = [];
            foreach ($pack['interface'] as $key => $value) {
                $old = $existing[$key] ?? null;
                if ($old !== null && trim($old) !== '' && !in_array($old, array_filter([
                    $pack['sources'][$key] ?? null, $legacy['interface'][$key] ?? null, $legacy['legacy_interface'][$key] ?? null,
                ], function ($value) { return $value !== null; }), true)) continue;
                if (array_key_exists($key, $existing)) {
                    DB::table('translations')->where('lang', 'tr')->where('lang_key', $key)->update(['lang_value' => $value, 'updated_at' => now()]);
                } else {
                    $rows[] = ['lang' => 'tr', 'lang_key' => $key, 'lang_value' => $value, 'created_at' => now(), 'updated_at' => now()];
                }
            }
            foreach (array_chunk($rows, 100) as $chunk) DB::table('translations')->insert($chunk);

            $names = array_change_key_case($legacy['products'], CASE_LOWER);
            DB::table('products')->select('id', 'name', 'description', 'unit')->orderBy('id')->chunkById(100, function ($products) use ($legacy, $names, $descriptionTexts) {
                $existing = DB::table('product_translations')->where('lang', 'tr')->whereIn('product_id', $products->pluck('id'))->get()->keyBy('product_id');
                foreach ($products as $product) {
                    $row = $existing->get($product->id);
                    $fields = [];
                    if (!$row || !$row->name || $row->name === $product->name) {
                        $fields['name'] = $legacy['products'][$product->name] ?? $names[strtolower($product->name)] ?? TurkishCopy::text($product->name, 'tr');
                    }
                    if (!$row || !$row->unit || $row->unit === $product->unit) $fields['unit'] = TurkishCopy::text($product->unit, 'tr');
                    if ($product->description && (!$row || !$row->description || $row->description === $product->description)) {
                        $translated = TurkishCopy::html($product->description, 'tr', $descriptionTexts);
                        if ($translated !== $product->description) $fields['description'] = $translated;
                    }
                    if (!$fields) continue;
                    $fields['updated_at'] = now();
                    if ($row) DB::table('product_translations')->where('id', $row->id)->update($fields);
                    else DB::table('product_translations')->insert($fields + ['product_id' => $product->id, 'lang' => 'tr', 'created_at' => now()]);
                }
            });
            foreach (['categories' => ['category_translations', 'category_id'], 'attributes' => ['attribute_translations', 'attribute_id']] as $source => $target) {
                foreach (DB::table($source)->get(['id', 'name']) as $item) {
                    $query = DB::table($target[0])->where($target[1], $item->id)->where('lang', 'tr');
                    $row = $query->first();
                    if ($row && trim((string) $row->name) !== '' && $row->name !== $item->name) continue;
                    $fields = ['name' => TurkishCopy::text($item->name, 'tr'), 'updated_at' => now()];
                    if ($row) $query->update($fields);
                    else DB::table($target[0])->insert($fields + [$target[1] => $item->id, 'lang' => 'tr', 'created_at' => now()]);
                }
            }
            foreach (DB::table('pages')->get() as $page) {
                $query = DB::table('page_translations')->where('page_id', $page->id)->where('lang', 'tr');
                $row = $query->first();
                $fields = [];
                foreach (['title', 'content'] as $field) {
                    if (!$row || !$row->$field || $row->$field === $page->$field) {
                        $fields[$field] = $field === 'content' ? TurkishCopy::html((string) $page->$field, 'tr') : TurkishCopy::text((string) $page->$field, 'tr');
                    }
                }
                if (!$fields) continue;
                $fields['updated_at'] = now();
                if ($row) $query->update($fields);
                else DB::table('page_translations')->insert($fields + ['page_id' => $page->id, 'lang' => 'tr', 'created_at' => now()]);
            }
            $settings = DB::table('business_settings')->whereIn('type', TurkishCopy::settingKeys())->get();
            foreach ($settings->groupBy('type') as $type => $options) {
                $source = $options->firstWhere('lang', 'en') ?: $options->firstWhere('lang', null) ?: $options->first();
                $row = $options->firstWhere('lang', 'tr');
                if ($row && trim((string) $row->value) !== '' && $row->value !== $source->value) continue;
                $value = $source->value;
                if ($value && in_array($type, ['widget_one_labels', 'header_menu_labels'], true)) {
                    $labels = json_decode($value, true);
                    if (is_array($labels)) $value = json_encode(array_map(function ($label) { return TurkishCopy::text($label, 'tr'); }, $labels), JSON_UNESCAPED_UNICODE);
                } else {
                    $value = TurkishCopy::html($value, 'tr');
                }
                if ($row) DB::table('business_settings')->where('id', $row->id)->update(['value' => $value, 'updated_at' => now()]);
                else DB::table('business_settings')->insert(['type' => $type, 'lang' => 'tr', 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        foreach (['translations-tr', 'business_settings', 'app.languages', 'all_categories', 'featured_categories'] as $key) Cache::forget($key);
    }

    public function down()
    {
        // Keep public copy and subsequent administrator edits.
    }
}
