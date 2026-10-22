<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReportDateRange
{
    public static function apply($query, $value)
    {
        if ($value === null || $value === '') {
            return;
        }

        $dates = is_string($value) ? preg_split('/\s*\/\s*/', trim($value)) : [];
        $validator = Validator::make([
            'start' => $dates[0] ?? null,
            'end' => $dates[1] ?? null,
        ], ['start' => 'required|date_format:Y-m-d', 'end' => 'required|date_format:Y-m-d|after_or_equal:start']);

        if (count($dates) !== 2 || $validator->fails()) {
            throw ValidationException::withMessages(['date_range' => translate('Invalid date range')]);
        }

        // Use an exclusive next-day boundary so all timestamps on the final day match.
        $query->where('created_at', '>=', Carbon::createFromFormat('!Y-m-d', $dates[0])->toDateTimeString())
            ->where('created_at', '<', Carbon::createFromFormat('!Y-m-d', $dates[1])->addDay()->toDateTimeString());
    }
}
