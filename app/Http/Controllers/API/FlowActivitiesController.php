<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ActivityApiResource;
use App\Models\Convert\Flow;
use App\Models\Intelligence\Activity;

class FlowActivitiesController extends Controller
{
    /**
     * Return a list of all activities for a flow (all scenarios)
     */
    public function index(Flow $flow)
    {
        $query = $flow
            ->activities()
            ->latest('created_at')
            ->with([
                'scenario',
                'flow',
                'actions',
                'collector',
                'customer',
                'collector.agent',
                'customer.twin',
                'customer.twin.connector'
            ])
            ->withCount(['views']);

        return ActivityApiResource::collection($query->simplePaginate());
    }

    public function destroy(Flow $flow, Activity $activity)
    {
        $activity->actions()->delete();
        $activity->delete();

        return response()->noContent();
    }
}
