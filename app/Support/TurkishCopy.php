<?php

namespace App\Support;

/** Static public copy only; customer messages and user-entered names are never translated. */
class TurkishCopy
{
    private static $pack;

    public static function pack()
    {
        if (self::$pack === null) {
            self::$pack = json_decode(file_get_contents(database_path('data/turkish_frontend.json')), true, 512, JSON_THROW_ON_ERROR);
        }
        return self::$pack;
    }

    public static function key($source)
    {
        return preg_replace('/[^A-Za-z0-9\_]/', '', str_replace(' ', '_', strtolower($source)));
    }

    public static function message($key)
    {
        return self::pack()['interface'][$key] ?? null;
    }

    public static function normalize($source)
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode((string) $source, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    public static function text($source, $locale = null)
    {
        if (($locale ?? app()->getLocale()) !== 'tr' || $source === null || $source === '') return $source;
        $normal = self::normalize($source);
        $translation = self::pack()['content'][$normal] ?? self::message(self::key($normal));
        return $translation === null ? $source : $translation;
    }

    public static function html($source, $locale = null, array $dictionary = null)
    {
        if (($locale ?? app()->getLocale()) !== 'tr' || !$source) return $source;
        // Keep tags, links, images, inline code and styles byte-for-byte intact.
        $blocks = preg_split('#(<!--.*?-->|<(?:script|style)\b[^>]*>.*?</(?:script|style)>)#is', $source, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($blocks as $index => $block) {
            if (preg_match('#^(?:<!--|<(?:script|style)\b)#i', $block)) continue;
            $blocks[$index] = preg_replace_callback('/(^|>)([^<]+)(?=<|$)/u', function ($match) use ($dictionary) {
                $normal = self::normalize($match[2]);
                if ($normal === '') return $match[0];
                $translated = $dictionary === null ? self::text($normal, 'tr') : ($dictionary[$normal] ?? $normal);
                if ($translated === $normal) return $match[0];
                preg_match('/^(\s*)/u', $match[2], $leading);
                preg_match('/(\s*)$/u', $match[2], $trailing);
                return $match[1].$leading[1].htmlspecialchars($translated, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').$trailing[1];
            }, $block);
        }
        return implode('', $blocks);
    }

    public static function settingKeys()
    {
        return ['site_motto','meta_title','meta_description','meta_keywords','about_us_description','widget_one','widget_one_labels',
            'header_menu_labels','footer_copyright_text','cookies_agreement_text','website_popup_content','contact_address'];
    }

    public static function setting($key, $value)
    {
        if (app()->getLocale() !== 'tr' || !in_array($key, self::settingKeys(), true) || !$value) return $value;
        if (in_array($key, ['widget_one_labels','header_menu_labels'], true)) {
            $labels = json_decode($value, true);
            return is_array($labels) ? json_encode(array_map([self::class, 'text'], $labels), JSON_UNESCAPED_UNICODE) : $value;
        }
        return self::html($value);
    }
}
