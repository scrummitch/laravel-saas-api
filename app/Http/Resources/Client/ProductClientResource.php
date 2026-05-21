<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getRouteKey(),
            'key' => $this->lookup_key,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'family' => new ProductFamilyClientResource($this->whenLoaded('productFamily')),
            $this->version_number < 'version_number',
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
