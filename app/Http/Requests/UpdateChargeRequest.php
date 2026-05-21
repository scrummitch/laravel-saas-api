<?php

namespace App\Http\Requests;

use App\Models\Catalog\Product;
use App\Models\Usage\Metric;
use App\Models\Values\PlanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChargeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('charge'));
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'product' => Product::retrieve($this->input('product'))?->id,
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
                'string',
            ],
            'type' => [
                'required',
                Rule::enum(PlanType::class),
            ],
            'mode' => [
                'required',
                Rule::in(['in_advance', 'in_arrears']),
            ],
            'minimum_billable_usage' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'amount' => [
                'required_if:type,standard,one_time',
                'numeric',
                'min:0',
            ],
            'amount_minimum_spend' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'currency' => [
                'required',
                Rule::in(['USD', 'EUR', 'GBP']),
            ],
            'product' => [
                'nullable',
                Rule::exists('catalog_products', 'id'),
            ],
        ];
    }
}
