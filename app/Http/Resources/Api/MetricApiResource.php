<?php

namespace App\Http\Resources\Api;

use App\Database\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MetricApiResource extends JsonResource
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
            'object' => 'metric',
            'name' => implode(' ', [
                '('.$this->feature->name.')',
                $this->event_name,
                '.',
                $this->field_name,
            ]),
            'event_name' => $this->event_name,
            'aggregation' => $this->aggregation,
            'type' => $this->type,
            'field_name' => $this->field_name,
            'weighted_interval' => $this->weighted_interval,
            'filters' => $this->filters,
            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,
        ];
    }
}
