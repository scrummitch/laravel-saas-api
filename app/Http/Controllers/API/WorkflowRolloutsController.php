<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\WorkflowApiResource;
use App\Models\Convert\Flow;
use App\Models\Publish\Rollout;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class WorkflowRolloutsController extends Controller
{
    public function update(Flow $workflow, Rollout $rollout, Request $request)
    {
        $validated = $request->validate([
            'is_active' => [
                'required',
                'boolean',
            ],
            'rules' => [
                'array',
            ],
            'rules.*' => [
                'array',
                'bail',
                function ($attribute, $value, $fail, Validator $validator) {
                    // value must have a "type" and a "value" keys
                    if (! isset($value['type']) || ! isset($value['value'])) {
                        $fail('The '.$attribute.' must have a "type" and a "value" keys.');
                    }

                    if (! in_array($value['type'], ['percentage'])) {
                        $fail('The '.$attribute.' is not a valid type.');
                    }

                    // cant have duplicates of the same type
                    if (count(array_filter($validator->getData()['rules'], fn ($rule) => $rule['type'] === $value['type'])) > 1) {
                        $fail('The '.$attribute.' must be unique.');
                    }

                    // all percentage type rollouts value must be min 0 and max 100
                    if ($value['type'] === 'percentage' && ($value['value'] < 0 || $value['value'] > 100)) {
                        $fail('The '.$attribute.' must be between 0 and 100.');
                    }
                },
            ],
        ]);

        $rollout->rules = Arr::get($validated, 'rules', $rollout->rules);
        $rollout->is_active = $validated['is_active'];
        $rollout->save();

        return new WorkflowApiResource($workflow->refresh());
    }
}
