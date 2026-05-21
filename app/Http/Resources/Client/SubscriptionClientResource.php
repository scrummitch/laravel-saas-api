<?php

namespace App\Http\Resources\Client;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'quantity' => $this->quantity,
            'plan' => new PlanClientResource($this->whenLoaded('plan')),
            $this->when(
                $this->relationLoaded('twin'),
                function () {
                    return $this->merge([
                        'is_trial' => $this->is_trial,
                    ]);
                }
            ),
        ];
    }
}
