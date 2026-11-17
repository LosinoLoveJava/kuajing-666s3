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
        return Country::where('status', 1)->whereIn('code', ['SG', 'TR'])->orderBy('code')->get(['code', 'name'])->map(function ($country) {
            return ['country' => strtoupper($country->code), 'name' => $country->name,
                'dial_code' => strtoupper($country->code) === 'TR' ? '+90' : '+65'];
        })->values()->all();
    }

    public function normalize($value, $country = null)
    {
        $number = preg_replace('/[\s().-]+/', '', (string) $value);
        if (strpos($number, '00') === 0) { $number = '+'.substr($number, 2); }
        $country = $country ? strtoupper($country) : ((strpos($number, '+90') === 0 || (strlen($number) === 12 && strpos($number, '90') === 0)) ? 'TR' : 'SG');
        if ($number !== '' && strpos($number, '+') !== 0) {
            if ($country === 'TR') {
                if (strlen($number) === 11 && $number[0] === '0') $number = substr($number, 1);
                $number = strlen($number) === 12 && strpos($number, '90') === 0 ? '+'.$number : '+90'.$number;
            } else {
                // Eight-digit Singapore numbers may themselves begin with 65.
                $number = strlen($number) === 10 && strpos($number, '65') === 0 ? '+'.$number : '+65'.$number;
            }
        }
        $patterns = ['SG' => '/^\+65[3689][0-9]{7}$/D', 'TR' => '/^\+905[0-9]{9}$/D'];
        if (!isset($patterns[$country]) || !preg_match($patterns[$country], $number)) {
            throw ValidationException::withMessages(['phone' => translate('Invalid phone number')]);
        }
        if (!Country::where('status', 1)->whereRaw('UPPER(code) = ?', [$country])->exists()) {
            throw ValidationException::withMessages(['phone' => translate('This phone country is not enabled.')]);
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
            throw ValidationException::withMessages(['phone' => translate('This phone country is not enabled.')]);
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
            throw ValidationException::withMessages(['phone' => translate('Multiple accounts use this number. Please contact support.')]);
        }
        return $users->first();
    }
}
