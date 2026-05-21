<?php

namespace App\Http\Requests;

use App\Models\Catalog\Feature;
use App\Models\Usage\AggregationValue;
use App\Models\Usage\Metric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMetricRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Metric::class);
    }

    protected function prepareForValidation()
    {
        $this->merge(array_filter([
            'feature_id' => Feature::retrieve($this->get('feature_id'))?->id,
        ]));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_name' => [
                'required',
            ],
            'feature_id' => [
                'required',
                Rule::exists(Feature::class, 'id')
                    ->where('organization_id', $this->user()->currentOrganization?->id),
            ],
            'type' => [
                'nullable',
                Rule::in(['persistent', 'transient']),
            ],
            'aggregation' => [
                'required',
                Rule::enum(AggregationValue::class),
            ],
        ];
    }
}
