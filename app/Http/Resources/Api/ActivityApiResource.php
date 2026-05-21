<?php

namespace App\Http\Resources\Api;

use App\Models\Intelligence\ActivityAction;
use App\Models\Stats\Collector;
use DeviceDetector\Parser\Client\Browser;
use DeviceDetector\Parser\OperatingSystem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Collector $collector
 * @property Collection $actions
 */
class ActivityApiResource extends JsonResource
{
    public function toArray(Request $request)
    {
        //customer_email
        //user_name
        //customer_id
        //user_name
        //placement
        //variant_name

        //exclusion_reason

        //total_views_count
        //last_interaction_at
        //latest_event
        return [
            'id' => $this->getRouteKey(),
            'current_state' => $this->current_state,
            'activity' => [
                'id' => $this->id,
                'has_entered' => $this->has_entered,
                'has_started' => $this->has_started,
                'has_completed' => $this->has_completed,
                'started_at' => $this->started_at,
                'finished_at' => $this->finished_at,
                'last_interaction_at' => $this->last_interaction_at,
                'current_state' => $this->current_state,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
                'migrate_session_id' => $this->migrate_session_id,
                'uuid' => $this->uuid,
            ],

            'customer' => $this->customer ? new \App\Http\Resources\Api\CustomerApiResource($this->customer) : null,

            'customer_id' => $this->customer?->getRouteKey(),
            'user_name' => $this->collector->agent?->name ?? $this->collector->agent?->email ?? $this->collector->agent?->getRouteKey(),
            'customer_email' => $this->customer?->email ?? $this->collector->customer?->email ?? null,
            'customer_created_at' => $this->collector->customer?->reference_created_at->timestamp ?? null,

            'placement' => $this->flow?->lookup_key,
            'variant_name' => $this->scenario->display_name,

            'handler' => $this->whenLoaded('handler', function () {
                return [
                    'id' => $this->handler->getRouteKey(),
                    'event_name' => $this->handler->event_name,
                ];
            }, null),

            // todo: handler!

            'flow' => $this->whenLoaded('flow', function () {
                return [
                    'id' => $this->flow->getRouteKey(),
                    'display_name' => $this->flow->name,
                ];
            }, null),

            'actions' => $this->whenLoaded('actions', function () {
                return $this->actions
                    ->sortBy('created_at')
                    ->map(function (ActivityAction $action) {
                    return [
                        'id' => $action->event_id,
                        'event_name' => $action->event_name,
                        'type' => $action->type->name,
                        'datetime' => $action->created_at->toIso8601String(),
                        'properties' => $action->properties,
                        'metadata' => $action->metadata,
                    ];
                });
            }, []),
            'total_views_count' => $this->views_count,

            'latest_event' =>  $this->whenLoaded('actions', function () {
                return $this->actions->last()?->type->name;
            }, null),

            'last_interaction_at' => $this->last_interaction_at?->timestamp,

            'country_flag' => empty($flag = $this->collector->countryFlag()) ? '🌍' : $flag,
            'country_code' => $this->collector->country,
            'country_name' => $this->collector->countryName() ?? 'Unknown',

            'os_code' => $this->collector->os,
            'os_name' => $this->collector->os ? OperatingSystem::getNameFromId($this->collector->os) : null,
            'browser_code' => $this->collector->browser,
            'browser_name' => $this->collector->browser ? Browser::getBrowserFamily($this->collector->browser) : null,

            'created_at' => $this->created_at->timestamp,
        ];
    }
}
