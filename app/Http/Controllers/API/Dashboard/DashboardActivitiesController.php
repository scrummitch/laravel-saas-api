<?php

namespace App\Http\Controllers\API\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ActivityApiResource;
use App\Models\Client;
use App\Models\Intelligence\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DashboardActivitiesController extends Controller
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $organization = $request->user()->currentOrganization;
        $client = Client::retrieve($request->get('client')) ?? $organization->liveClient();

        $activities = Activity::query()
            ->where('client_id', $client->id)
            ->latest('id')
            ->with([
                'scenario',
                'flow',
                'actions',
                'collector.agent',
                'customer.twin.connector',
            ])
            ->withCount(['views'])
            ->simplePaginate();

        return ActivityApiResource::collection($activities);
    }
}
