<?php

namespace App\Http\Resources;

use App\Http\Resources\Api\PlanApiResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageApiResource extends JsonResource
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
            'display_name' => $this->display_name,
            'scheme' => $this->scheme ? [
                'id' => $this->scheme->getRouteKey(),
                'name' => $this->scheme->name,
            ] : null,
            'plans' => $this->whenLoaded('plans', fn () => PlanApiResource::collection($this->plans)),
        ];
    }
}
