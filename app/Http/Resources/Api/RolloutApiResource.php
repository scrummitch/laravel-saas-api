<?php

namespace App\Http\Resources\Api;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RolloutApiResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->getRouteKey(),
            'object' => 'rollout',
            'is_active' => $this->is_active,
            'rules' => $this->rules,
            'percentage' => $this->percentage(),
            'client' => $this->whenLoaded('client', function (Client $client) {
                return [
                    'id' => $client->getRouteKey(),
                    'object' => 'client',
                    'name' => $client->name,
                    'environment' => $client->environment,
                ];
            }),
            'participations_count' => $this->whenCounted('participations', fn () => $this->participations_count, null),
            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,
        ];
    }
}
