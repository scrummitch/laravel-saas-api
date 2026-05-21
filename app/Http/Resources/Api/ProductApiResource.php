<?php

namespace App\Http\Resources\Api;

use App\Models\Catalog\ProductFeature;
use App\Models\Twin;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class ProductApiResource extends JsonResource
{
    public static $wrap = false;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // todo: status

        return [
            'id' => $this->getRouteKey(),
            'internal_id' => $this->when(! app()->isProduction(), $this->getKey()),
            'name' => $this->name,
            $this->when(
                $this->relationLoaded('productFamily'),
                function () {
                    return $this->merge([
                        'family' => $this->family,
                    ]);
                }
            ),
            'display_name' => $this->display_name,
            'description' => $this->description,
            'created_at' => $this->created_at?->timestamp,
            'updated_at' => $this->updated_at?->timestamp,
            'family' => new ProductFamilyApiResource($this->whenLoaded('productFamily')),
            'version_number' => $this->version_number,

            // note: product twins dont matter anymore?
            'features' => $this->whenLoaded('productFeatures', function (Collection $collection) {
                return $collection->map(function (ProductFeature $pf) {
                    return [
                        'id' => $pf->feature->getRouteKey(),
                        'name' => $pf->feature->name,
                        'allowance' => $pf->allowance,
                        'unit' => $pf->unit,
                        'reset_period' => $pf->reset_period,
                        'created_at' => $pf->created_at?->timestamp,
                        'updated_at' => $pf->updated_at?->timestamp,
                    ];
                });
            }),

            'twins' => $this->whenLoaded('twins', function (Collection $collection) {
                return $collection->map(function (Twin $twin) {
                    return [
                        'id' => $twin->reference_id,
                        'type' => $twin->type,
                        'data' => $twin->data,
                        'provider_id' => $twin->connector->lookup_key,
                        'provider_type' => $twin->connector->type,
                        'environment' => $twin->connector->environment,
                        'connector_link' => $twin->url(),
                    ];
                });
            }),
            'status' => $this->status,
            'archived_at' => $this->archived_at?->timestamp,
            'published_at' => $this->published_at?->timestamp,
        ];
    }
}
