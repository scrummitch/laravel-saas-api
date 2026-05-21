<?php

namespace App\Http\Requests;

use App\Models\Convert\Element;
use App\Models\Convert\Flow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateElementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('element'));
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
                Rule::unique(Element::class, 'display_name')
                    ->ignore($this->route('element')->id)
                    ->where('organization_id', $this->user()->organization_id),
            ],
//            'current_state' => [
//                'required',
//                Rule::in(['draft', 'active', 'inactive', 'archived']),
//            ],
            'lookup_key' => [
                'min:3',
                Rule::unique(Element::class, 'lookup_key')
                    ->ignore($this->route('element')->id)
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'conditions' => [
            ],
            'view' => [
                'required',
            ],
            'template' => [
                'nullable'
            ],
        ];
    }
}
