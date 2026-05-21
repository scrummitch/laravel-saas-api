<?php

namespace App\Billing;

use Carbon\CarbonInterval;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class IntervalCast implements CastsAttributes
{
    public function get($model, $key, $value, $attributes): ?CarbonInterval
    {
        return self::match($value);
    }

    public static function match($value)
    {
        return match ($value) {
            null => null,
            'year', 'month', 'week', 'day' => CarbonInterval::{$value}(),
            'yearly' => CarbonInterval::year(),
            'monthly' => CarbonInterval::month(),
            'weekly' => CarbonInterval::week(),
            'daily' => CarbonInterval::day(),
            default => self::try($value),
        };
    }

    public static function try($value)
    {
        try {
            return CarbonInterval::create($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function set($model, $key, $value, $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof CarbonInterval) {
            // Convert to a storable string format, e.g., "1 month", "2 weeks"
            return $value->spec();
        }

        return (string) $value;
    }
}
