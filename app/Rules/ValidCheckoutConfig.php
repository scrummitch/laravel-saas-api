<?php

namespace App\Rules;

use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class ValidCheckoutConfig implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $lineItems = collect($value)
            ->map(function ($li) use ($fail) {
                return match (Arr::get($li, 'object')) {
                    'plan' => value(function () use ($li, $fail) {
                        /** @var Plan|null */
                        $plan = Plan::retrieve($li['id']);

                        if (is_null($plan)) {
                            $fail('Plan with name '.$li['id'].' does not exist');
                        } elseif (! $plan?->isActive()) {
                            $fail('Plan '.$li['id'].' is not active');
                        }

                        return $plan;
                    }),
                    'package' => value(function () use ($li, $fail) {
                        /** @var Package|null */
                        $package = Package::retrieve($li['id']);

                        if (is_null($package)) {
                            $fail('Package with name '.$li['id'].' does not exist');
                        }

                        return $package;
                    }),
                    default => $fail('Invalid object type'),
                };
            });

        // only do currency verification if there are line items that are plans
        if ($lineItems->contains(fn ($li) => $li instanceof Plan)) {
            $this->validateCurrencies($lineItems, $fail, $attribute);
        }
    }

    private function validateCurrencies(\Illuminate\Support\Collection $lineItems, Closure $fail, string $attribute)
    {
        $currencies = $lineItems->pluck('currency')->unique();

        if ($currencies->count() > 1) {
            $fail($attribute.' must not contain multiple currencies');
        }
    }
}
