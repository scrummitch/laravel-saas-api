<?php

namespace App\Http\Resources\Api;

use App\Billing\ISO4217;
use App\Models\Catalog\Inclusion;
use App\Models\Values\PlanType;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * {@inheritDoc \App\Models\Catalog\Plan}
 *
 * @property int $organization_id
 * @property PlanType $type
 * @property string $display_name
 * @property string $description
 * @property string $name
 * @property CarbonInterval $renew_interval
 * @property string $billing_anchor // calendar, anniversary
 * @property CarbonInterval $invoice_interval
 * @property \Money\Currency $currency
 */
class PlanApiResource extends JsonResource
{
    public static $wrap = null;

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

            'status' => $this->status,
            'type' => $this->type,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'name' => $this->name,

            'renew_interval' => $this->renew_interval?->spec(),

            'billing_anchor' => $this->billing_anchor,
            'invoice_interval' => $this->invoice_interval?->spec(),

            'currency_code' => $currency->getAlpha3(),
            'currency_symbol' => $currency->getSymbol(),
            'currency_flag' => $currency->getFlag(),

            'trial_length' => $this->trial_length,
            'trial_credit' => $this->trial_credit,
            'trial_unit' => $this->trial_unit,

            'package' => $this->whenLoaded('package', fn () => [
                'id' => $this->package->getRouteKey(),
                'object' => 'package',
                'name' => $this->package->name,
            ], null),

            'charges' => ChargeApiResource::collection($this->whenLoaded('charges')),

            'inclusions' => InclusionApiResource::collection($this->whenLoaded('inclusions')),

            $this->when($this->relationLoaded('inclusions'), function () {
                return $this->merge([
                    'products' => $this->inclusions
                        ->where('product_id', '!=', null)
                        ->where('feature_id', null)
                        ->map(fn (Inclusion $inclusion) => [
                            'id' => $inclusion->product->getRouteKey(),
                            'family' => $inclusion->product?->productFamily?->getRouteKey(),
                            'object' => 'product',
                        ])
                        ->values(),
                ]);
            }),

            'subscriptions_count' => $this->whenCounted('subscriptions'),

            'internal_id' => $this->when(app()->hasDebugModeEnabled(), $this->id),

            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,
        ];
    }
}
