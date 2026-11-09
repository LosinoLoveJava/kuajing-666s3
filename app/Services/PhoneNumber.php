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
        return Country::where('status', 1)->whereRaw('UPPER(code) = ?', ['SG'])->get(['name'])->map(function ($country) {
            return ['country' => 'SG', 'name' => $country->name, 'dial_code' => '+65'];
        })->values()->all();
    }

    public function normalize($value, $country = null)
    {
        $number = preg_replace('/[\s().-]+/', '', (string) $value);
        if (strpos($number, '00') === 0) { $number = '+'.substr($number, 2); }
        if ($number !== '' && strpos($number, '+') !== 0) {
            // Eight-digit local numbers may themselves begin with 65.
            $number = strlen($number) === 10 && strpos($number, '65') === 0 ? '+'.$number : '+65'.$number;
        }
        if (($country && strtoupper($country) !== 'SG') || !preg_match('/^\+65[3689][0-9]{7}$/D', $number)) {
            throw ValidationException::withMessages(['phone' => translate('Invalid phone number')]);
        }
        if (!Country::where('status', 1)->whereRaw('UPPER(code) = ?', ['SG'])->exists()) {
            throw ValidationException::withMessages(['phone' => 'This phone country is not enabled.']);
        }
        return $number;
    }

    public function normalizeAdmin($value)
    {
        // Preserve existing staff phone authentication independently of public accounts.
        $number = preg_replace('/[\s().-]+/', '', (string) $value);
        if (strpos($number, '00') === 0) { $number = '+'.substr($number, 2); }
        if (preg_match('/^(?:\+?65)?[3689][0-9]{7}$/D', $number)) {
            return $this->normalize($value);
        }
        if (preg_match('/^(?:\+?971[1-9][0-9]{7,8}|0?[1-9][0-9]{7,8})$/D', $number)) {
            $number = strpos($number, '+971') === 0 ? $number
                : (strpos($number, '971') === 0 ? '+'.$number : '+971'.ltrim($number, '0'));
            if (Country::where('status', 1)->whereRaw('UPPER(code) = ?', ['AE'])->exists()) {
                return $number;
            }
            throw ValidationException::withMessages(['phone' => 'This phone country is not enabled.']);
        }
        return $this->normalize($value);
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
