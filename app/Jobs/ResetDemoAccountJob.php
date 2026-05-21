<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Intelligence\Activity;
use App\Models\Management\Organization;
use App\Models\Stats\Collector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;
use Illuminate\Support\Str;

class ResetDemoAccountJob extends QueueableJob
{
    public function handle()
    {
        $orgId = config('app.demo_organization');

        if (empty($orgId)) {
            logger()->info('No demo organization configured');

            return;
        }

        $organization = Organization::retrieve($orgId);

        if (!$organization) {
            logger()->info('Demo organization not found', [
                'organization_id' => $orgId,
            ]);

            return;
        }

        $testClient = $organization->liveClient();

        $shouldDeleteCount = DB::table('intel_activities')
            ->whereIn('scenario_id', $organization->scenarios->pluck('id'))
            ->count();

        DB::table('intel_actions')
            ->join('intel_activities', 'intel_actions.activity_id', '=', 'intel_activities.id')
            ->whereIn('intel_activities.scenario_id', $organization->scenarios->pluck('id'))
            ->delete();

        $deletes = DB::table('intel_activities')
            ->whereIn('scenario_id', $organization->scenarios->pluck('id'))
            ->delete();

        DB::table('store_purchases')
            ->where(['organization_id' => $organization->id])
            ->delete();

        $attributionsDeleteCount = DB::table('convert_attributions')
            ->where('producer_type', 'paywall')
            ->whereIn('producer_id', $organization->scenarios->pluck('id'))
            ->delete();

        $collectorDeleteCount = DB::table('stats_collectors')
            ->join('intel_activities', 'stats_collectors.id', '=', 'intel_activities.collector_id')
            ->whereIn('intel_activities.scenario_id', $organization->scenarios->pluck('id'))
            ->where('stats_collectors.client_id', $testClient->id)
            ->delete();

        logger()->info('Deleted paywall sessions', [
            'organization_id' => $organization->id,
            'should_delete' => $shouldDeleteCount,
            'total_deleted' => $deletes,
            'attributions_delete_count' => $attributionsDeleteCount,
            'collector_delete_count' => $collectorDeleteCount,
        ]);

        $organization->loadMissing([
            'scenarios',
            'clients',
            'workflows',
        ]);
        $days = 31;

        for ($i = 0; $i < $days; $i++) {
            $this->createRandomUsageForDay($organization, now()->startOfDay()->subDays($i), $testClient);
        }

        // TODO: replace
        Activity::query()
            ->whereIn('scenario_id', $organization->scenarios->pluck('id'))
            ->whereNotNull('finished_at')
            ->with(['scenario'])
            ->orderBy('intel_activities.id')
            ->each(function ($activity) use ($organization, $testClient) {
                $charge = Cache::remember('demo_charge_' . $activity->scenario_id, now()->addMinutes(5), function () use ($activity, $organization) {
                    return $activity->scenario
                        ->bundleItems()
                        ->first()
                        ?->purchasable
                        ->charges()
                        ->first() ?? $organization->charges()->first();
                });

                DB::table('store_purchases')
                    ->insert([
                        'organization_id' => $organization->id,
                        'current_state' => 'completed',
                        'customer_id' => $activity->customer_id,
                        'provider_name' => 'plandalf',
                        'provider_id' => Str::random(32),
                        'intent' => 'upgrade',
                        'billing_provider_id' => $organization->test_billing_provider_id ?? 0,
                        'currency' => 'USD',
                        'created_at' => now(),
                    ]);

                DB::table('convert_attributions')
                    ->insert([
                        'producer_id' => $activity->scenario_id,
                        'producer_type' => 'scenario',
                        'session_id' => $activity->id,
                        'type' => 'upgrade',
                        'proceeds_amount_gross' => $charge?->amount->getAmount(),
                        'proceeds_amount_net' => $charge?->amount->getAmount(),
                        'purchase_type' => 'twin',
                        'purchase_id' => 0,
                        'currency' => 'USD',
                        'activity_id' => $activity->id,
                    ]);
            });
    }

    private function createRandomUsageForDay(Organization $organization, Carbon $day, Client $client)
    {
        $insertions = [];

        // Determine if it's a weekday (Monday to Friday)
        $isWeekday = $day->isWeekday();
        $customer = $organization->customers()->first();

        foreach ($organization->scenarios as $index => $paywall) {
            // Create more sessions on weekdays
            $baseSessions = $isWeekday ? random_int(40, 100) : random_int(20, 60);
            $numSessions = $baseSessions;

            for ($i = 0; $i < $numSessions; $i++) {
                $collector = new Collector();
                $collector->client_id = $client->id;
                $collector->save();

                $hasViewed = true; // All sessions are viewed
                $hasStarted = (random_int(1, 100) <= random_int(30, 50)); // 30-50% of views become starts
                $hasCompleted = $hasStarted && (random_int(1, 100) <= 20); // 20% of starts become conversions

                $enteredAt = $day->copy()->startOfDay()->startOfHour()->addHours(random_int(0, 23))->addMinutes(random_int(0, 59));
                $startedAt = $hasStarted ? $enteredAt->copy()->addMinutes(random_int(1, 10)) : null;
                $completedAt = $hasCompleted ? $startedAt->copy()->addMinutes(random_int(1, 15)) : null;
                $lastInteractionAt = $completedAt ?? $startedAt ?? $enteredAt;

                if (Lottery::odds(1, 5)->choose()) {

                    $insertions[] = [
                        'uuid' => (string) \Illuminate\Support\Str::uuid(),
                        'scenario_id' => $paywall->id,
                        'customer_id' => $customer?->id,
                        'agent_id' => $organization->id,
                        'client_id' => $collector->client_id,
                        'collector_id' => $collector->id,
//                        'placement' => 'demo',
//                        'entered_at' => $enteredAt,
                        'started_at' => null,
                        'finished_at' => null,
                        'last_interaction_at' => $lastInteractionAt,
                        'has_entered' => $hasViewed,
                        'has_started' => false,
                        'has_completed' => false,
                        'created_at' => $enteredAt,
                    ];
                }

                $insertions[] = [
                    'uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'scenario_id' => $paywall->id,
                    'customer_id' => $customer?->id,
                    'agent_id' => $organization->id,
                    'client_id' => $collector->client_id,
                    'collector_id' => $collector->id,
//                    'placement' => 'demo',
//                    'entered_at' => $enteredAt,
                    'started_at' => $startedAt,
                    'finished_at' => $completedAt,
                    'last_interaction_at' => $lastInteractionAt,
                    'has_entered' => $hasViewed,
                    'has_started' => $hasStarted,
                    'has_completed' => $hasCompleted,
                    'created_at' => $enteredAt,
                ];
            }
        }

        foreach ($insertions as $insertion) {
            try {
                DB::table('intel_activities')
                    ->insert($insertion);
            } catch (\Throwable $e) {
                dd($e);
            }
        }
    }
}
