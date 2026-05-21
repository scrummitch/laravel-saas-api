<?php

namespace App\Http\Requests;

use App\Models\Catalog\Feature;
use App\Models\Client;
use App\Models\Convert\Flow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkflowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Flow::class);
    }

    protected function prepareForValidation()
    {
        $this->merge([
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
                'min:3',
                Rule::unique((new Flow)->getTable(), 'name')
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'lookup_key' => [
                'required',
                'min:3',
                Rule::unique((new Flow)->getTable(), 'lookup_key')
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'handlers' => [
                'array',
            ],
        ];
    }
}
