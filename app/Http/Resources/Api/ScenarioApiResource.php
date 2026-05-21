<?php

namespace App\Http\Resources\Api;

use App\Models\Convert\BundleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScenarioApiResource extends JsonResource
{
    public static $wrap = false;

    public function toArray(Request $request)
    {
        return [
            'internal_id' => $this->id,
            'id' => $this->getRouteKey(),
            'flow' => [
                'id' => $this->flow->getRouteKey(),
                'display_name' => $this->flow->name,
            ],
            'display_name' => $this->display_name,
            'properties' => $this->properties,

            'scheme' => $this->whenLoaded('scheme', function () {
                return [
                    'id' => $this->scheme->getRouteKey(),
                ];
            }, null),

            'intent' => $this->intent,
            'conditions' => $this->conditions,

            'element' => $this->whenLoaded('element', function () {
                return [
                    'id' => $this->element->getRouteKey(),
                ];
            }, null),

            'purchasables' => $this->bundleItems->map(function (BundleItem $bundleItem) {
                return [
                    'id' => $bundleItem->purchasable->getRouteKey(),
                    'object' => $bundleItem->purchasable_type,
                ];
            }),

            'stats' => $this->whenHas('stats', fn () => $this->stats, null),
        ];
    }
}
