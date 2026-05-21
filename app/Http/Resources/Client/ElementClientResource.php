<?php

namespace App\Http\Resources\Client;

use App\Models\Convert\Handler;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ElementClientResource extends JsonResource
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
            'type' => $this->type,
            'insertion_type' => $this->insertion_type,
            'insertion_rules' => $this->insertion_rules,
            'conditions' => $this->conditions,
            'event_handlers' => $this->handlers->mapWithKeys(function (Handler $handler) {
                return [$handler->event_name => [
                    'id' => $handler->getRouteKey(),
                    'flow' => $handler->flow->getRouteKey(),
                ]];
            }),
        ];
    }
}
