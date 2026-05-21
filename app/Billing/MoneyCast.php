<?php

namespace App\Billing;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Money\Currency;
use Money\Money;

class MoneyCast implements CastsAttributes
{
    public function get($model, $key, $value, $attributes)
    {
        /** @phpstan-ignore property.notFound */
        $currency = $model->currency;

        if (empty($value)) {
            return new Money(0, $currency);
        }

        return new Money($value, $currency);
    }

    public function set($model, $key, $value, $attributes)
    {
        return $value instanceof Money ? $value->getAmount() : $value;
    }
}
