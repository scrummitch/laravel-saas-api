<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionApiResource extends JsonResource
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
            'id' => $this->getRouteKey(),
            'internal_id' => $this->id,
            'plan' => new PlanApiResource($this->whenLoaded('plan')),
            'previous' => $this->whenLoaded('previousSubscription', fn () => new self($this->previousSubscription), null),
            'quantity' => $this->quantity,
            'current_state' => $this->current_state,
            'start_at' => $this->start_at?->timestamp,
            'end_at' => $this->end_at?->timestamp,
            'cancel_at' => $this->cancel_at?->timestamp,
            'invoiced_at' => $this->invoiced_at?->timestamp,
            'renewed_at' => $this->renewed_at?->timestamp,
            'created_at' => $this->created_at?->timestamp,
            'updated_at' => $this->updated_at?->timestamp,
            'twin' => $this->whenLoaded('twin', fn () => $this->twin, null),
//            'reference_created_at' => $this->reference_created_at?->timestamp,
        ];
    }
}
