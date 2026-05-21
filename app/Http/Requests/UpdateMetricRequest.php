<?php

namespace App\Http\Requests;

use App\Models\Catalog\Feature;
use App\Models\Usage\AggregationValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMetricRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('metric'));
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'feature_id' => Feature::retrieve($this->get('feature_id'))?->id,
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
            'description' => [
                'nullable',
            ],
            'event_name' => [
                'nullable',
            ],
            'field_name' => [
                'nullable',
            ],
            'feature_id' => [
                'nullable',
                Rule::exists(Feature::class, 'id')
                    ->where('organization_id', $this->user()->currentOrganization?->id),
            ],
            'type' => [
                'nullable',
                Rule::in(['persistent', 'transient']),
            ],
            'aggregation' => [
                'nullable',
                Rule::enum(AggregationValue::class),
            ],
        ];
    }
}
