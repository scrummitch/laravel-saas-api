<?php

namespace App\Http\Requests;

use App\Models\Values\PlanType;
use Carbon\CarbonInterval;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('plan'));
    }

    protected function prepareForValidation()
    {
        logger()->info(logname(), $this->all());
    }

    public function rules(): array
    {
        return [
            'type' => [
                Rule::in(PlanType::cases()),
            ],
            'display_name' => [
                'string',
                'min:3',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'name' => [
                'string',
                'min:3',
            ],
//            'renew_interval' => [
//                function (string $attribute, mixed $value, Closure $fail) {
//                    if (is_null(CarbonInterval::create($value))) {
//                        $fail("The {$attribute} is invalid.");
//                    }
//                },
//            ],
            'billing_anchor' => [
                Rule::in(['calendar', 'anniversary']),
            ],
            'invoice_interval' => [
                function (string $attribute, mixed $value, Closure $fail) {
                    if (is_null(CarbonInterval::create($value))) {
                        $fail("The {$attribute} is invalid.");
                    }
                },
            ],
            'currency' => [
                Rule::in(['USD', 'EUR', 'GBP', 'JPY', 'CNY', 'AUD', 'NZD', 'CAD', 'INR', 'RUB']),
            ],
//            'trial_length' => [
//                'nullable',
//            ],
//            'trial_credit' => [
//                'nullable',
//            ],
//            'trial_unit' => [
//                'nullable',
//            ],
        ];
    }
}
