<?php

namespace App\Http\Requests;

use App\Models\Billing\Charge;
use App\Models\Catalog\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInclusionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('plan'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $orgId = $this->user()->currentOrganization->id;

        return [
            'product' => [
                'nullable',
                Rule::exists((new Product)->getTable(), (new Product)->getRouteKeyName())
                    ->where('organization_id', $orgId),
            ],
            'charge' => [
                'nullable',
                Rule::exists((new Charge)->getTable(), (new Charge)->getRouteKeyName())
                    ->where('organization_id', $orgId),
            ],
        ];
    }
}
