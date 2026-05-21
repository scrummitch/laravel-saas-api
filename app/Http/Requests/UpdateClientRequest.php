<?php

namespace App\Http\Requests;

use App\Models\Billing\BillingProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('client'));
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'billing_provider_id' => BillingProvider::retrieve($this->input('billing_provider_id'))?->id,
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
                'max:128',
                Rule::unique('clients')
                    ->where('organization_id', $this->user()->currentOrganization->id)
                    ->ignore($this->route('client')->id),
            ],
            'billing_provider_id' => [
                'nullable',
                'integer',
                Rule::exists('billing_providers', 'id')
                    ->where('organization_id', $this->user()->currentOrganization->id),
            ],
            'allowed_origins' => [
                'array',
                function ($attribute, $value, $fail) {
                    if (count($value) !== count(array_unique($value))) {
                        $fail('The :attribute must be unique.');
                    }
                },
            ],
            'exclusion_rules' => ['nullable', 'array'],
            'exclusion_rules.*.rule' => ['required', 'string', Rule::in(['emailDomainIs'])],
            'exclusion_rules.*.value' => [
                'nullable',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                        $fail('The :attribute must be a valid domain name.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'exclusion_rules.*.rule.in' => 'The exclusion rule must be "emailDomainIs".',
            'exclusion_rules.*.value.max' => 'The exclusion rule value must not exceed 255 characters.',
        ];
    }
}
