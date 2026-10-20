<?php

namespace App\Services;

use App\Models\Country;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PhoneNumber
{
    public function metadata()
    {
        return json_decode(file_get_contents(resource_path('phone_countries.json')), true);
    }

    public function countries()
    {
        $metadata = $this->metadata();
        return Country::where('status', 1)->get(['name', 'code'])->filter(function ($country) use ($metadata) {
            return isset($metadata[strtolower($country->code)]);
        })->map(function ($country) use ($metadata) {
            return ['country' => strtoupper($country->code), 'name' => $country->name,
                'dial_code' => '+'.$metadata[strtolower($country->code)]['dial_code']];
        })->values()->all();
    }

    public function normalize($value, $country = null)
    {
        $number = preg_replace('/[\s().-]+/', '', (string) $value);
        if (strpos($number, '00') === 0) {
            $number = '+'.substr($number, 2);
        }
        if (!preg_match('/^\+[1-9][0-9]{6,14}$/D', $number)) {
            throw ValidationException::withMessages(['phone' => 'Use an international phone number, for example +971501234567.']);
        }
        $digits = substr($number, 1);
        $matches = [];
        $longest = 0;
        foreach ($this->metadata() as $iso => $data) {
            $prefixes = empty($data['area_codes']) ? [$data['dial_code']] : array_map(function ($area) use ($data) {
                return $data['dial_code'].$area;
            }, $data['area_codes']);
            foreach ($prefixes as $prefix) {
                if (strpos($digits, $prefix) === 0) {
                    $length = strlen($prefix);
                    if ($length > $longest) { $matches = []; $longest = $length; }
                    if ($length === $longest) { $matches[] = $iso; }
                }
            }
        }
        if ($country) {
            $matches = array_values(array_intersect($matches, [strtolower($country)]));
        }
        $allowed = Country::where('status', 1)->whereIn('code', array_map('strtoupper', $matches))->exists();
        // MySQL country codes are case-insensitive; keep SQLite and other installations equivalent.
        if (!$allowed) {
            $allowed = Country::where('status', 1)->whereIn('code', $matches)->exists();
        }
        if (!$matches || !$allowed) {
            throw ValidationException::withMessages(['phone' => 'This phone country is not enabled.']);
        }
        return $number;
    }

    public function user($phone)
    {
        $digits = substr($phone, 1);
        $query = User::where(function ($query) use ($phone, $digits) {
            $query->where('phone_normalized', $phone)->orWhere('phone', $phone)->orWhere('phone', $digits)
                ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', '') = ?", [$digits]);
        });
        $users = $query->limit(2)->get();
        if ($users->count() > 1) {
            throw ValidationException::withMessages(['phone' => 'Multiple accounts use this number. Please contact support.']);
        }
        return $users->first();
    }
}
