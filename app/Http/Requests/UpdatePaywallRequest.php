<?php

namespace App\Http\Requests;

use App\Models\Pricing\Scheme;
use App\Rules\ValidCheckoutConfig;
use App\Rules\ValidConditionSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaywallRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('paywall'));
    }

    public function prepareForValidation()
    {
        $this->merge([
            'pricing_scheme_id' => Scheme::retrieve($this->input('scheme_id'))?->id,
            'stages' => json_decode($this->input('stages'), true),
            'conditions' => json_decode($this->input('conditions'), true),
            'checkout_config' => json_decode($this->input('checkout_config'), true),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:4',
                'max:128',
            ],
            'pricing_scheme_id' => [
                'required',
                'integer',
                Rule::exists(Scheme::class, 'id')
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'intent' => [
                'required',
                Rule::in([
                    'upgrade',
                    'expansion',
                    'addon',
                    'purchase',
                    // setup
                ]),
            ],
            'mode' => [
                'required',
                Rule::in([
                    'payment',
                    'setup',
                ]),
            ],
            'stages' => [
                'array',
            ],
            'conditions' => [
                'array',
                new ValidConditionSchema,
            ],
            'checkout_config' => [
                'required',
                new ValidCheckoutConfig,
            ],
            'can_change_interval_on_expansion' => ['boolean'],
        ];

    }
}
