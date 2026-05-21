<?php

namespace App\Billing;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class CurrencyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes)
    {
        return !empty($value) ? new \Money\Currency($value) : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes)
    {
        return match(true) {
            is_null($value) => null,
            is_string($value) => $value,
            $value instanceof \Money\Currency => $value->getCode(),
            $value instanceof Currency => $value->getAlpha3(),
            default => $value->getCode(),
        };
    }
}
