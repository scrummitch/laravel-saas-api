<?php

namespace App\Http\Requests;

use App\Models\Catalog\Product;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductFamilyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'selected_product_ids' => collect($this->get('selected_products', []))->map(fn ($productId) => array_pad(explode('@v', $productId), 2, 1)[0])->toArray(),
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
            'name' => 'required|string',
            'lookup_key' => 'nullable|string',
            'asset_id' => [
                function ($attribute, $value, $fail) {
                    if (! is_null($value) && ! \App\Models\Media\Asset::retrieve($value)) {
                        $fail('The asset_id must be a valid Asset.');
                    }
                },
            ],
            'selected_product_ids' => [
                'nullable',
                'array',
                function ($attribute, $productIds, $fail) {
                    if (! is_null($productIds)) {
                        $products = Product::query()
                            ->whereIn('lookup_key', $productIds)
                            ->where('organization_id', $this->user()->currentOrganization->id)
                            ->get();

                        if (count($productIds) !== $products->count()) {
                            $fail('The selected_products includes invalid product id');
                        }

                        $invalidProduct = $products->first(fn (Product $product) => ! is_null($product->product_family_id));

                        if ($invalidProduct) {
                            $fail("Invalid selected_products: You can't update a product that's already connected to other family.");
                        }
                    }
                },
            ],
        ];
    }
}
