<?php

namespace App\Http\Requests;

use App\Models\Billing\BillingProvider;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Client::class);
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
                Rule::unique('clients', 'name')
                    ->where('organization_id', $this->get('organization_id'))
                    ->where('type', $this->get('type') ?? 'web'),
            ],
            'type' => [
                'required',
                'string',
                Rule::in(['web', 'mobile', 'server']),
            ],
            'platform' => [
                'nullable',
                'string',
                'max:32',
            ],
            'billing_provider_id' => [
                'nullable',
                'integer',
                Rule::exists('billing_providers', 'id')
                    ->where('organization_id', $this->user()->currentOrganization->id),
            ],
        ];
    }
}
