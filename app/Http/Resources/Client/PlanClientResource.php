<?php

namespace App\Http\Resources\Client;

use App\Billing\Currency;
use App\Billing\ISO4217;
use App\Models\Billing\Charge;
use App\Models\Catalog\Inclusion;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @property \Money\Currency $currency
 * @property CarbonInterval $renew_interval
 * @property CarbonInterval $invoice_interval
 * @property Collection<Charge> $charges
 */
class PlanClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currency = ISO4217::make($this->currency);

        return [
            'id' => $this->getRouteKey(),
            'object' => 'plan',

            'type' => $this->type,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'name' => $this->name,

            'package' => $this->whenLoaded('package', function () {
                return [
                    'id' => $this->package->getRouteKey(),
                    'object' => 'package',
                    'name' => $this->package->name,
                ];
            }, null),

            'renew_interval' => $this->renew_interval->spec(),
            'invoice_interval' => $this->invoice_interval ? $this->invoice_interval?->spec() : $this->renew_interval->spec(),

            'currency' => $currency->getAlpha3(),
            'currency_name' => $currency->getName(),
            'currency_symbol' => $currency->getSymbol(),

            'trial_length' => $this->trial_length,
            'trial_credit' => $this->trial_credit,
            'trial_unit' => $this->trial_unit,

            'charges' => ChargeClientResource::collection($this->whenLoaded('charges')),

            $this->when($this->relationLoaded('inclusions'), function () {
                $deprecatedFamily = $this->getProductFamilies()?->first();

                return $this->merge([
                    'products' => $this->inclusions
                        ->where('product_id', '!=', null)
                        ->where('feature_id', null)
                        ->map(function (Inclusion $inclusion) {
                            return [
                                'id' => $inclusion->product->getRouteKey(),
                                'family' => $inclusion->product?->productFamily?->getRouteKey(),
                                'object' => 'product',
                            ];
                        }),

                    // deprecated
                    'product_family' => $deprecatedFamily ? [
                        'lookup_key' => $deprecatedFamily->getRouteKey(),
                    ] : null,
                    'product_key' => $this->getProductLookupKey(),
                ]);
            }),
        ];
    }
}
