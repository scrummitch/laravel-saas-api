<?php

namespace App\Http\Controllers\API\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Analytics\DashboardStatsService;
use App\Services\Analytics\StatDateRange;
use Illuminate\Http\Request;

class DashboardActivityController extends Controller
{
    public function __invoke(Request $request)
    {
        $organization = $request->user()->currentOrganization;
        $client = Client::retrieve($request->get('client')) ?? $organization->liveClient();

        $stats = new DashboardStatsService(
            range: StatDateRange::last_30_days,
            client: $client,
        );

        return $stats->getActivityTimeSeries();
    }
}
