<?php

namespace App\Rules;

use App\Convert\Enums\Conditions\Combinator;
use App\Convert\Enums\Conditions\Criteria;
use App\Convert\Enums\Conditions\CriteriaComparisonOperators;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ValidConditionSchema implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $conditions, Closure $fail): void
    {
        if (is_null($conditions)) {
            return;
        }

        $rules = [
            'rules' => ['array'],
            'combinator' => [Rule::enum(Combinator::class)],
            'rules.*.criteria' => ['required', Rule::enum(Criteria::class)],
            'rules.*.arg' => 'nullable',
            'rules.*.op' => [Rule::enum(CriteriaComparisonOperators::class)],
            'rules.*.value' => 'nullable',
        ];

        $validator = Validator::make($conditions, $rules);
        if (! $validator->passes()) {
            $this->failErrors($validator->errors()->all(), $fail);
        }
    }

    public function failErrors(array $errors, Closure $fail)
    {
        foreach ($errors as $error) {
            $fail($error);
        }
    }
}
