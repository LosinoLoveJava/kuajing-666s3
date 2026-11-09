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
            $number = strlen($number) === 10 && strpos($number, '65') === 0 ? '+'.$number : '+65'.$number;
        }
        // Public account entrypoints accept Singapore national or international numbers.
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
        // Existing operator accounts may still have an explicitly entered UAE number.
        $number = preg_replace('/[\s().-]+/', '', (string) $value);
        if (strpos($number, '00') === 0) { $number = '+'.substr($number, 2); }
        if (preg_match('/^\+?971[1-9][0-9]{7,8}$/D', $number)) {
            if (!Country::where('status', 1)->whereRaw('UPPER(code) = ?', ['AE'])->exists()) {
                throw ValidationException::withMessages(['phone' => 'This phone country is not enabled.']);
            }
            return '+'.ltrim($number, '+');
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
