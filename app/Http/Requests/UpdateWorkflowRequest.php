<?php

namespace App\Http\Requests;

use App\Models\Catalog\Feature;
use App\Models\Client;
use App\Models\Convert\Flow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkflowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('flow') !== null;
    }

    protected function prepareForValidation()
    {
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
                    ->ignore($this->route('flow')->id)
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'lookup_key' => [
                'min:3',
                Rule::unique((new Flow)->getTable(), 'lookup_key')
                    ->ignore($this->route('flow')->id)
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'handlers' => [
                'array',
            ],
        ];
    }
}
