<?php

namespace App\Http\Requests;

use App\Models\Convert\Element;
use App\Models\Convert\Flow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreElementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Element::class);
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
            ],
            'type' => [
                'required',
                Rule::in([
                    'paywall_modal',
                ])
            ],
            'mode' => [
                'required',
                Rule::in(['managed', 'tracked']),
            ],
            'lookup_key' => [
                'required',
                'min:3',
                Rule::unique((new Element())->getTable(), 'lookup_key')
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'conditions' => [
            ],
        ];
    }
}
