<?php

namespace App\Http\Requests;

use App\Models\Billing\Charge;
use App\Models\Usage\Metric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInclusionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('inclusion'));
    }

    protected function prepareForValidation()
    {
        $input = $this->input('metric');

        if (! is_null($input)) {
            $this->merge([
                'metric' => Metric::retrieve($input)?->id ?? $input,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $orgId = $this->user()->currentOrganization->id;

        return [
            'metric' => [
                'nullable',
                Rule::exists(Metric::class, 'id')
                    ->where('organization_id', $orgId),
            ],
            'default_limit' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'limit_unit' => ['sometimes', 'nullable', 'string', 'max:64'],
            'reset_anchor' => ['sometimes', 'nullable', 'string', 'max:64'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'charge' => ['sometimes', 'nullable', 'array'],
            'charge.id' => [
                'required_with:charge',
                Rule::exists((new Charge)->getTable(), 'id')
                    ->where('organization_id', $orgId),
            ],
        ];
    }
}
