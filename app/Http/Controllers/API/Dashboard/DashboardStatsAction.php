<?php

namespace App\Http\Controllers\API\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Management\Organization;
use App\Services\Analytics\DashboardStatsService;
use App\Services\Analytics\StatDateRange;
use Illuminate\Http\Request;

class DashboardStatsAction extends Controller
{
    public function __invoke(Request $request, Organization $org)
    {
        $client = Client::retrieve($request->get('client')) ?? $org->liveClient();

        $stats = new DashboardStatsService(
            range: StatDateRange::tryFrom($request->get('range', 'last_30_days')),
            client: $client,
            timezone: $request->input('tz', 'UTC'),
        );

        return [
            'stats' => $stats->getGlobalStats(),
        ];
    }
}
