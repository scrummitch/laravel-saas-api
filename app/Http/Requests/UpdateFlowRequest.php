<?php

namespace App\Http\Requests;

use App\Models\Convert\Flow;
use App\Rules\ValidConditionSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdateFlowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('flow'));
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
                    ->ignore($this->route('flow')->id)
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'lookup_key' => [
                'min:3',
                Rule::unique((new Flow())->getTable(), 'lookup_key')
                    ->ignore($this->route('flow')->id)
                    ->where('organization_id', $this->user()->organization_id),
            ],
            'scenarios' => [
                'array',
            ],
            'scenarios.*.conditions' => [
                new ValidConditionSchema(),
            ],
            'scenarios.*' => [
                'required',
                function ($attribute, $value, $fail) {
                    // needs to be a map with keys: conditions, action, context
                    // conditions is checked above, action is STRING
                    // context is a map of arbitrary k/v
                    if (!is_array($value)) {
                        $fail('Scenario must be an object with conditions, action, and context keys');
                    }

                    if (!array_key_exists('action', $value)) {
                        $fail('Scenario must have an action key');
                    }

                    if (!is_string(Arr::get($value, 'action'))) {
                        $fail('Scenario action must be a string');
                    }

                    if (!array_key_exists('context', $value)) {
                        $fail('Scenario must have a context key');
                    }

                    if (!is_array(Arr::get($value, 'context'))) {
                        $fail('Scenario context must be an object');
                    }

                    return;
                },
            ],
            'inactivity_timeout_seconds' => [
                'integer',
                'nullable',
                'min:0',
            ],
        ];
    }
}
