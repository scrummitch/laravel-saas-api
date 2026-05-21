<?php

namespace App\Http\Resources\Api;

use App\Database\Model;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $released_at
 */
class FeatureResource extends JsonResource
{
    public static $wrap = false;

    public function toArray(Request $request)
    {
        return [
            'id' => $this->getRouteKey(),
            'object' => 'feature',
            'name' => $this->name,
            'description' => $this->description,
            'feature_set' => new FeatureSetApiResource($this->whenLoaded('featureSet')),
            'metrics' => [
                'object' => 'list',
                'data' => $this->whenLoaded('metrics', function () {
                    return MetricApiResource::collection($this->metrics);
                }, []),
            ],
            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,
            'released_at' => $this->released_at?->timestamp,
        ];
    }
}
