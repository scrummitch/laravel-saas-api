<?php

namespace App\Convert;

use App\Client\ClientAuthorization;
use App\Convert\Enums\ElementMode;
use App\Convert\Enums\ElementType;
use App\Models\Intelligence\Activity;
use App\Models\Intelligence\Scenario;
use App\Models\Management\Organization;
use App\Models\Pricing\Plan;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;

class PaywallService
{
    public function __construct(
        public Scenario $scenario,
        public ClientAuthorization $auth,
        public Request $request
    )
    {
    }

    public static function resolveScenario(Organization $organization, ?string $key = null): Scenario
    {
        if (is_null($key)) {
            throw new ModelNotFoundException('No ID passed');
        }

        return Scenario::query()
            ->whereHas('flow', function ($query) use ($organization) {
                $query->where('organization_id', $organization->id);
            })
            ->where('lookup_key', $key)
            ->firstOrFail();
    }

    public function getActivity()
    {
        if ($this->request->filled('activity')) {
            return Activity::retrieve($this->request->get('activity'));
        }

        $activity = Activity::query()
            ->where('last_interaction_at', '>', now()->subHour())
            ->firstOrNew([
                'collector_id' => $this->auth->collector->id,
                'scenario_id' => $this->scenario->id,
            ]);

        if ($activity->exists) {
            return $activity;
        }

        $activity = Activity::query()->firstOrCreate([
            'uuid' => Str::uuid()->toString(),
            'client_id' => $this->auth->client->id,
        ], [
            'collector_id' => $this->auth->collector->id,
            'scenario_id' => $this->scenario?->id,
            'customer_id' => $this->auth->customer?->id,
            'current_state' => 'created',
            'has_entered' => true,
            'last_interaction_at' => now(),
        ]);

        return $activity;
    }

    public static function migrateToActivities(): void
    {
        // ignore foreign key constraints
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('convert_elements')->truncate();
        DB::table('intel_scenarios')->truncate();
        // if i deleted all scenarios, theres probably still some activities with old scenario IDs in there
        // what do they look like?
        DB::table('convert_bundle_items')->truncate();
        DB::table('store_purchases')->truncate();
        DB::table('store_purchase_items')->truncate();
        DB::table('convert_handlers')->truncate();

        DB::table('convert_workflows')
            ->oldest('id')
            ->each(function ($flow) {
                foreach (json_decode($flow->triggers) ?? [] as $trigger) {
                    $handler = [
                        'uuid' => Str::uuid()->toString(),
                        'element_id' => null,
                        'listen' => data_get($trigger, 'listen'),
                        'qualifier' => data_get($trigger, 'qualifier'),
                        'props' => empty(data_get($trigger, 'props')) ? null : json_encode($trigger->props),
                        'flow_id' => $flow->id,
                        'event_name' => data_get($trigger, 'event'),
                    ];

                    DB::table('convert_handlers')
                        ->insert($handler);
                }
            });

        DB::table('convert_paywalls')
            ->oldest('id')
            ->each(function ($paywall) {

                $element = [
                    'organization_id' => $paywall->organization_id,
                    'lookup_key' => 'paywall_'.(new Ulid($paywall->ulid))->toBase58(),
                    'type' => ElementType::Paywall->value,
                    'mode' => ElementMode::Managed->value,
                    'properties' => json_encode([
                        'mode' => 'subscription',
                    ]),
                    'conditions' => null,
                    'view' => $paywall->stages,
                ];

                $elementId = DB::table('convert_elements')->insertGetId($element);

                $items = collect(data_get(json_decode($paywall->checkout_config), 'line_items', []))
                    ->filter(fn ($li) => isset($li->object) && isset($li->id))
                    ->map(function ($li) use ($paywall) {
                        return match($li->object) {
                            'plan' => DB::table('pricing_plans')
                                ->where('lookup_key', $li->id)
                                ->where('organization_id', $paywall->organization_id)
                                ->first(),
                            'package' => DB::table('pricing_packages')
                                ->where('lookup_key', $li->id)
                                ->where('organization_id', $paywall->organization_id)
                                ->first(),
                            default => null
                        };
                    })
                    ->filter();
                $plan = $items
                    ->filter(fn ($item) => isset($item->renew_interval))
                    ->first();

                $scenario = [
                    'flow_id' => $paywall->workflow_id,
                    'scheme_id' => $paywall->pricing_scheme_id,
                    'element_id' => $elementId,
                    'lookup_key' => 'paywall_'.(new Ulid($paywall->ulid))->toBase58(),
                    'organization_id' => $paywall->organization_id,
                    'display_name' => $paywall->name,
                    'intent' => $paywall->intent,
                    'properties' => json_encode([
                        'mode' => $paywall->mode,
                        'can_change_interval_on_expansion' => data_get(json_decode($paywall->settings), 'can_change_interval_on_expansion') ?? false,
                    ]),
                    'renew_interval' => $plan?->renew_interval,
                    'conditions' => $paywall->conditions,
                ];

                $scenarioId = DB::table('intel_scenarios')->insertGetId($scenario);

                $bundleItems = $items->map(function ($item) use ($scenarioId) {
                    return [
                        'purchasable_id' => $item->id,
                        'purchasable_type' => isset($item->renew_interval) ? 'plan' : 'package',
                        'scenario_id' => $scenarioId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                });

                DB::table('convert_bundle_items')
                    ->insert($bundleItems->toArray());

                DB::table('convert_paywalls')
                    ->where('id', $paywall->id)
                    ->update([
                        'migrate_element_id' => $elementId,
                        'migrate_scenario_id' => $scenarioId,
                    ]);
            });

        DB::table('convert_paywall_sessions')
            ->join('convert_paywalls', 'convert_paywall_sessions.paywall_id', '=', 'convert_paywalls.id')
            ->select('convert_paywalls.migrate_element_id', 'convert_paywalls.migrate_scenario_id', 'convert_paywall_sessions.*')
            ->oldest('id')
            ->each(function ($session) {
                $activity = [
                    'client_id' => $session->client_id,
                    'scenario_id' => $session->migrate_scenario_id,
                    'uuid' => $session->cart_token ?? Str::uuid()->toString(),
                    'collector_id' => $session->collector_id,
                    'customer_id' => $session->customer_id,
                    'agent_id' => $session->agent_id,

                    'current_state' => $session->has_completed ? 'completed' : ($session->has_started ? 'started' : 'created'),

                    'has_entered' => $session->has_entered,
                    'has_started' => $session->has_started,
                    'has_completed' => $session->has_completed,

                    // ignore exclusions
                    'error_reason' => null,
                    'result_code' => null,
                    'result_message' => null,
                    'migrate_session_id' => $session->id,

                    'started_at' => $session->started_at,
                    'finished_at' => $session->completed_at,
                    'created_at' => $session->created_at,
                    'updated_at' => $session->updated_at,
                    'last_interaction_at' => $session->last_interaction_at,
                ];

                $activityId = DB::table('intel_activities')
                    ->insertGetId($activity);
            });

        DB::table('intel_actions')
            ->insertUsing(
                [
                    'activity_id',
                    'event_id',
                    'created_at',
                    'properties',
                    'metadata',
                    'event_name',
                    'type'
                ],
                DB::table('convert_paywall_events')
                    ->join('intel_activities', 'convert_paywall_events.session_id', '=', 'intel_activities.migrate_session_id')
                    ->select([
                        DB::raw('intel_activities.id as activity_id'),
                        DB::raw('uuid() as event_id'),
                        'convert_paywall_events.created_at',
                        'convert_paywall_events.data as properties',
                        DB::raw('NULL as metadata'),
                        DB::raw('NULL as event_name'),
                        DB::raw('CASE
                    WHEN convert_paywall_events.name = "entry" THEN 0
                    WHEN convert_paywall_events.name = "start" THEN 1
                    ELSE 31
                END as type')
                    ])
            );

        DB::table('convert_checkouts')
            // join paywall sessions on session_id
            ->join('intel_activities', 'convert_checkouts.session_id', '=', 'intel_activities.migrate_session_id')
            ->join('account_customers', 'convert_checkouts.customer_id', '=', 'account_customers.id')
            ->select('convert_checkouts.*', 'intel_activities.migrate_session_id', 'intel_activities.id as activity_id', 'account_customers.billing_provider_id')
            ->latest('id')
            ->each(function ($checkout) {
                $config = json_decode($checkout->config);

                $items = collect(json_decode($checkout->line_items));

                $renewInterval = data_get($checkout->config, 'renew_interval');

                $pmId = DB::table('twins')
                    ->where('reference_id', data_get($config, 'payment_method'))
                    ->value('id');

                if (empty($checkout->billing_provider_id)) {
                    return;
                }

                $purchase = [
                    'organization_id' => $checkout->organization_id,
                    'activity_id' => $checkout->activity_id,
                    'current_state' => $checkout->current_state,
                    'customer_id' => $checkout->customer_id,
                    'provider_id' => $checkout->token, // or ulid?
                    'provider_name' => 'plandalf',
                    'currency' => $checkout->currency,

                    'billing_provider_id' => $checkout->billing_provider_id, // in config?

                    'intent' => 'upgrade',
                    'payment_method_id' => $pmId, // could be in config?
                    'renew_interval' => $renewInterval, // could be in config? // ? needs fixing later

                    'expires_at' => null,
                    'completed_at' => $checkout->completed_at,
                    'created_at' => $checkout->created_at,
                    'updated_at' => $checkout->updated_at,
                ];

                $purchaseId = DB::table('store_purchases')
                    ->insertGetId($purchase);

                $purchaseItems = $items->map(function ($item) use ($purchaseId) {

                    $purchasable = match ($item->object) {
                        'plan' => Plan::query()
                            ->where('lookup_key', $item->id)
                            ->first(),
                        default => null,
                    };

                     return [
                        'purchasable_type' => 'plan',
                        'purchasable_id' => $purchasable->id,
                        'purchase_id' => $purchaseId,
                        'quantity' => data_get($item, 'quantity', 1),
                        'amount_discount' => null,
                        'amount_tax' => null,
                        'amount_total' => null,
                        'amount_subtotal' => null,
                     ];
                });

                if ($renewInterval === null && $purchaseItems->isNotEmpty()) {
                    $renewInterval = DB::table('pricing_plans')
                        ->where('id', $purchaseItems->first()['purchasable_id'])
                        ->value('renew_interval');

                    DB::table('store_purchases')
                        ->where('id', $purchaseId)
                        ->update(['renew_interval' => $renewInterval]);
                }

                // must be unique ?
                DB::table('store_purchase_items')
                    ->insert($purchaseItems->toArray());
            });

        DB::table('convert_attributions')
            ->join('intel_activities', 'convert_attributions.session_id', '=', 'intel_activities.migrate_session_id')
            ->update([
                'activity_id' => DB::raw('intel_activities.id'),
            ]);
    }

}
