<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InclusionApiResource extends JsonResource
{
    public static $wrap = false;

    public function toArray(Request $request)
    {
        return [
            'id' => $this->getRouteKey(),
            'object' => 'inclusion',

            'product' => $this->product?->getRouteKey(),
            'feature' => $this->feature?->getRouteKey(),

            'metric' => $this->metric?->getRouteKey(),

            'plan' => $this->plan?->getRouteKey(),
            'charge' => $this->whenLoaded('charge', fn () => new ChargeApiResource($this->charge), null),

            'name' => $this->name,
            'default_limit' => $this->default_limit,
            'limit_unit' => $this->limit_unit,
            'reset_anchor' => $this->reset_anchor,
            'is_approved' => $this->is_approved,

            'created_at' => $this->created_at?->timestamp,
            'updated_at' => $this->updated_at?->timestamp,
//            'grouping_key' => $this->grouping_key,
//            'grouping_label' => $this->grouping_label,
//            'display_name' => $this->display_name,
//            'description' => $this->description,
//            'note' => $this->note,
        ];
    }
}
