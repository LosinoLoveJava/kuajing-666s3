<?php

namespace App\Services;

class Iban
{
    public static function normalize($value)
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $value));
    }

    public static function valid($value, $country)
    {
        $value = self::normalize($value);
        $length = config('iban.lengths.'.strtoupper($country));
        if (!$length || strlen($value) !== $length || substr($value, 0, 2) !== strtoupper($country)
            || !preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/D', $value)) {
            return false;
        }
        $remainder = 0;
        foreach (str_split(substr($value, 4).substr($value, 0, 4)) as $char) {
            $digits = ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }
        return $remainder === 1;
    }
}
