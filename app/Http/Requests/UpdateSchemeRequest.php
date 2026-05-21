<?php

namespace App\Http\Requests;

use App\Models\Catalog\Product;
use App\Models\Pricing\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class UpdateSchemeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('scheme'));
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
                'string',
                'max:255',
            ],
            'packages' => [
                'array',
                function ($attribute, $value, $fail) {
                    foreach (Arr::wrap($value) as $productId) {
                        if (! Package::retrieve($productId)) {
                            $fail("Package with ID {$productId} does not exist");
                        }
                    }
                },
            ],
        ];
    }
}
