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
        return Country::where('status', 1)->whereRaw('UPPER(code) = ?', ['AE'])->get(['name'])->map(function ($country) {
            return ['country' => 'AE', 'name' => $country->name, 'dial_code' => '+971'];
        })->values()->all();
    }

    public function normalize($value, $country = null)
    {
        $number = preg_replace('/[\s().-]+/', '', (string) $value);
        if (strpos($number, '00') === 0) { $number = '+'.substr($number, 2); }
        if ($number !== '' && strpos($number, '+') !== 0) {
            $number = strpos($number, '971') === 0 ? '+'.$number : '+971'.ltrim($number, '0');
        }
        // All account phone entrypoints use the UAE calling code, including API/legacy clients.
        if (($country && strtoupper($country) !== 'AE') || !preg_match('/^\+971[1-9][0-9]{7,8}$/D', $number)) {
            throw ValidationException::withMessages(['phone' => translate('Invalid phone number')]);
        }
        if (!Country::where('status', 1)->whereRaw('UPPER(code) = ?', ['AE'])->exists()) {
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
