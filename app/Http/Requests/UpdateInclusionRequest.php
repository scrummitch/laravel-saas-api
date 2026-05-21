<?php

namespace App\Http\Requests;

use App\Models\Usage\Metric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInclusionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('inclusion'));
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'metric' => Metric::retrieve($this->input('metric'))?->id,
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
            'metric' => [
                'nullable',
                Rule::exists(Metric::class, 'id'),
            ],
        ];
    }
}
