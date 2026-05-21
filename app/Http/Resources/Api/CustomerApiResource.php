<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class CustomerApiResource extends JsonResource
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
            ...parent::toArray($request),
            'id' => $this->getRouteKey(),
            'internal_id' => $this->id,
            'subscriptions' => $this->whenLoaded('subscriptions', fn () => SubscriptionApiResource::collection($this->subscriptions)),
            'schedule' => $this->whenLoaded('schedule', fn () => new ScheduleApiResource($this->schedule)),
            'hostname' => $this->getHostnameFromEmail($this->email),
            'reference_created_at' => $this->reference_created_at?->timestamp,
            'twin' => $this->twin ?  [
                'id' => $this->twin->reference_id,
                'object' => 'twin',
                'type' => $this->twin->type,
                'data' => $this->twin->data,
                'name' => Arr::get($this->twin, 'data.name', Arr::get($this->twin, 'data.nickname')),
                'provider' => $this->twin->connector->getRouteKey(),
                'environment' => $this->twin->connector->environment,
                'connector_link' => $this->twin->url(),
            ] : null,
        ];
    }

    private function getHostnameFromEmail(mixed $email)
    {
        return @explode('@', $email)[1];
    }
}
