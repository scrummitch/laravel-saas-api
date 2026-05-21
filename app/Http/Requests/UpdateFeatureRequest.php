<?php

namespace App\Http\Requests;

use App\Models\Catalog\Feature;
use App\Models\Catalog\FeatureSet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeatureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('feature'));
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'feature_set_id' => FeatureSet::retrieve($this->input('feature_set_id'))?->id,
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
                'nullable',
                'string',
            ],
            'lookup_key' => [
                'required',
                'string',
                Rule::unique(Feature::class, 'lookup_key')
                    ->where('organization_id', $this->user()->currentOrganization->id)
                    ->ignore($this->route('feature')->id),
            ],
            'feature_set_id' => [
                'required',
                Rule::exists(FeatureSet::class, 'id')
                    ->where('organization_id', $this->user()->currentOrganization->id),
            ],
        ];
    }
}
