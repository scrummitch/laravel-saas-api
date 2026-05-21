<?php

namespace App\Http\Resources\Api;

use App\Billing\ChargeFormatter;
use App\Billing\ISO4217;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Money\Money;

class PaywallApiResource extends JsonResource
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
            'object' => 'paywall',

            'scheme' => new SchemeApiResource($this->whenLoaded('scheme')),
            'workflow' => new WorkflowApiResource($this->whenLoaded('workflow')),

            'name' => $this->name,
            'mode' => $this->mode,
            'intent' => $this->intent,
            'weight' => $this->weight,
            'type' => $this->type,
            'settings' => $this->settings,

            //            'template' => $this->template,
            'stages' => $this->stages,
            'conditions' => $this->conditions,
            'checkout_config' => $this->checkout_config,

            'stats' => $this->whenHas('stats', function () {
                return $this->stats;
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
