<?php

namespace App\Http\Requests;

use App\Models\Convert\Flow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFlowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Flow::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'display_name' => [
                'required',
                'min:3',
                Rule::unique((new Flow())->getTable(), 'display_name')
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'lookup_key' => [
                'min:3',
                Rule::unique((new Flow())->getTable(), 'lookup_key')
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'scenarios' => [
                'array',
            ],
            'inactivity_timeout_seconds' => [
                'integer',
                'nullable',
                'min:0',
            ],
        ];
    }
}
