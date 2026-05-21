<?php

namespace App\Console\Commands;

use App\Models\Account\Customer;
use App\Models\Intelligence\Activity;
use App\Models\Management\Organization;
use App\Models\Twin;
use Illuminate\Console\Command;

class BonjoroFixCustomerInActivitiesTable extends Command
{
    protected $signature = 'app:bonjoro-fix-customer-in-activities-table';

    protected $description = 'Command description';

    public function handle()
    {
        $bonjoroOrg = Organization::query()
            ->where('name', 'Bonjoro')
            ->first();

        if(!$bonjoroOrg) return;

        $bonjoroTeamIds = Activity::query()
            ->join('stats_collectors', 'intel_activities.collector_id', '=', 'stats_collectors.id')
            ->join('account_agents', 'stats_collectors.agent_id', '=', 'account_agents.id')
            ->select('account_agents.lookup_key', 'stats_collectors.id')
            ->where('account_agents.organization_id', $bonjoroOrg->id)
            ->where('intel_activities.customer_id', null)
            ->whereNotNull('account_agents.lookup_key')
            ->distinct()
            ->pluck('lookup_key', 'stats_collectors.id');

        logger()->info('bonjoro:updating customer in activities table', [
            'total' => $bonjoroTeamIds->count(),
            'mappings' => $bonjoroTeamIds->map(fn($teamId, $collectorId) => [
                'collector_id' => $collectorId,
                'team_id' => $teamId
            ])->values()->toArray()
        ]);

        foreach ($bonjoroTeamIds as $collectorId => $teamId) {
            $twin = Twin::query()
                ->where('type', 'Stripe\Customer')
                ->where('data->metadata->team', $teamId)
                ->first();

            if($twin) {
                $customer = Customer::query()
                ->where('reference_id', $twin->reference_id)
                ->first();

                if($customer) {
                    Activity::query()
                    ->where('collector_id', $collectorId)
                    ->where('customer_id', null)
                    ->update([
                        'customer_id' => $customer->id,
                    ]);
                }
            }
        }

        $this->info("Completed");
    }
}
