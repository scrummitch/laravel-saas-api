<?php

namespace App\Http\Requests;

use App\Models\Catalog\Feature;
use App\Models\Catalog\FeatureSet;
use App\Models\Usage\AggregationValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeatureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Feature::class);
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
            'lookup_key' => 'required|string',
            'name' => 'required|string',

            'feature_set_id' => [
                'required',
                Rule::exists(FeatureSet::class, 'id')
                    ->where('organization_id', $this->user()->currentOrganization->id),
            ],

            'released_at' => 'nullable|date',

            'metric' => 'array',
            'metric.event_name' => [
                'required_with:metric',
            ],
            'metric.aggregation' => [
                'required_with:metric',
                'string',
                Rule::in(AggregationValue::cases()),
            ],
            'metric.type' => [
                'required_with:metric',
                Rule::in(['persistent', 'transient']),
            ],
            'metric.field_name' => [
                'required_with:metric',
            ],
        ];
    }
}
