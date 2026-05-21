<?php

namespace App\Http\Resources\Api;

use App\Billing\ChargeFormatter;
use App\Models\Catalog\Inclusion;
use App\Models\Twin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ChargeApiResource extends JsonResource
{
    public static $wrap = false;

    public function toArray(Request $request)
    {
        return [
            'id' => $this->getRouteKey(),
            'object' => 'charge',
            'product' => $this->whenLoaded('product', function () {
                return [
                    'object' => 'product',
                    'id' => $this->product->getRouteKey(),
                ];
            }),
            'type' => $this->type,
            'mode' => $this->mode,
            'name' => $this->name,

            'inclusions' => $this->whenLoaded('inclusions', function () {
                return $this->inclusions->map(function (Inclusion $inclusion) {
                    return new InclusionApiResource($inclusion);
                });
            }, []),

            'amount' => $this->amount->getAmount(),
            'minimum_billable_usage' => $this->minimum_billable_usage,
            'amount_minimum_spend' => $this->amount_minimum_spend?->getAmount(),
            'amount_formatted' => ChargeFormatter::format($this->amount),
            'currency' => $this->amount->getCurrency()->getCode(),

            'properties' => $this->properties,

            'twins' => $this->whenLoaded('twins', function () {
                return $this->twins->map(function (Twin $twin) {
                    return [
                        'id' => $twin->reference_id,
                        'object' => 'twin',
                        'type' => $twin->type,
                        'data' => $twin->data,
                        'name' => Arr::get($twin, 'data.name', Arr::get($twin, 'data.nickname')),
                        'provider' => $twin->connector->getRouteKey(),
                        'environment' => $twin->connector->environment,
                        'connector_link' => $twin->url(),
                    ];
                });
            }),
        ];
    }
}
