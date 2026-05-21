<?php

namespace App\Http\Requests;

use App\Models\Pricing\Scheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Scheme::class);
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'ancestor_id' => Scheme::retrieve($this->input('ancestor_id'))?->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'lookup_key' => [
                'required',
                'nullable',
                'string',
            ],
            'name' => [
                'required',
                'min:4',
            ],
            'ancestor_id' => [
                'nullable',
                Rule::exists('pricing_schemes', 'id')
                    ->where('organization_id', $this->user()->currentOrganization->id),
            ],
        ];
    }
}
