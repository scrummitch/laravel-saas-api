<?php

namespace App\Http\Resources\Api;

use App\Models\Media\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Asset $icon
 */
class ProductFamilyApiResource extends JsonResource
{
    public static $wrap = false;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getRouteKey(),
            'name' => $this->name,
            'organization' => new OrganizationApiResource($this->whenLoaded('organization')),
            'lookup_key' => $this->lookup_key,
            'icon' => $this->resource->icon ? [
                'id' => $this->icon->getRouteKey(),
                'href' => $this->icon->href('product_family_icon'),
            ] : null,
            'products' => ProductApiResource::collection($this->whenLoaded('products')),
        ];
    }
}
