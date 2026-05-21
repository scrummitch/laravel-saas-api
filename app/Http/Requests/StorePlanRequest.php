<?php

namespace App\Http\Requests;

use App\Models\Values\PlanType;
use Carbon\CarbonInterval;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                Rule::in(PlanType::cases()),
            ],
            'display_name' => [
                'required',
                'string',
                'min:3',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'name' => [
                'required',
                'string',
                'min:3',
            ],
            'renew_interval' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (is_null(CarbonInterval::create($value))) {
                        $fail("The {$attribute} is invalid.");
                    }
                },
            ],
            'billing_anchor' => [
                'required',
                Rule::in(['calendar', 'anniversary']),
            ],
            'invoice_interval' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (is_null(CarbonInterval::create($value))) {
                        $fail("The {$attribute} is invalid.");
                    }
                },
            ],
            'currency' => [
                'required',
                Rule::in(['USD', 'EUR', 'GBP', 'JPY', 'CNY', 'AUD', 'NZD', 'CAD', 'INR', 'RUB']),
            ],
            'trial_length' => [
                'nullable',
            ],
            'trial_credit' => [
                'nullable',
            ],
            'trial_unit' => [
                'nullable',
            ],
        ];
    }
}
