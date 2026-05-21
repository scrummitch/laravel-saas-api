<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductFeatureResource extends JsonResource
{
    public static $wrap = false;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->feature->getRouteKey(),
            'name' => $this->name ?? $this->feature->name,
            'description' => $this->description,
            'note' => $this->note,
            'allowance' => $this->allowance,
            'unit' => $this->unit,
            'reset_period' => $this->reset_period,
            'created_at' => $this->created_at,
        ];
    }
}
