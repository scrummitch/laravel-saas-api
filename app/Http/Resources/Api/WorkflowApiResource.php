<?php

namespace App\Http\Resources\Api;

use App\Convert\DataObjects\WorkflowTrigger;
use App\Models\Convert\BundleItem;
use App\Models\Convert\Handler;
use App\Store\IsPurchasable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorkflowApiResource extends JsonResource
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
            'object' => 'workflow',
            'name' => $this->name,
            'display_name' => $this->name ?? $this->display_name,

            'current_state' => $this->current_state?->name,

            'handlers' => $this->handlers->map(function (Handler $handler) {
                return [
                    'id' => $handler->getRouteKey(),
                    'object' => 'handler',
                    'listen' => $handler->listen,
                    'qualifier' => $handler->qualifier,
                    'event_name' => $handler->event_name,
                    'props' => $handler->props,
                ];
            }),
            'trigger_descriptors' => Collection::make($this->triggers)
                ->map(fn (WorkflowTrigger $trigger) => $trigger->descriptor())->toArray(),

            'scenarios_count' => $this->whenLoaded('scenarios', fn () => $this->scenarios->count(), null),

            'purchasables' => $this
                ->getAllPurchasables()
                ->map(function (BundleItem $bundleItem) {
                    $purchasable = $bundleItem->purchasable;

                    return [
                        'id' => $purchasable->getRouteKey(),
                        'object' => $purchasable->getMorphClass(),
                        'name' => $purchasable->getDisplayName(),
                    ];
                })
                ->values(),

            'stats' => $this->whenHas('stats', function () {
                if (! $this->stats) {
                    return null;
                }

                return [
                    'sessions_count' => intval($this->stats->views),
                    'conversions_count' => intval($this->stats->conversions),
                    'conversion_rate' => $this->stats->conversions > 0 ? round(($this->stats->conversions / $this->stats->views) * 100) : null,
                ];
            }),

            'images' => AssetApiResource::collection($this->whenLoaded('images')),

            'created_at' => $this->created_at->timestamp,
            'updated_at' => $this->updated_at->timestamp,
        ];
    }
}
