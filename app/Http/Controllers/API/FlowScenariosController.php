<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ScenarioApiResource;
use App\Models\Client;
use App\Models\Convert\Element;
use App\Models\Convert\Flow;
use App\Models\Intelligence\Scenario;
use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use App\Rules\ValidCheckoutConfig;
use App\Rules\ValidConditionSchema;
use App\Services\Analytics\DashboardStatsService;
use App\Services\Analytics\StatDateRange;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class FlowScenariosController extends Controller
{
    /**
     * List all scenarios for a given flow
     */
    public function index(Flow $flow, Request $request)
    {
        $scenarios = $flow->scenarios;

        $client = Client::retrieve($request->get('client')) ?? $flow->organization->liveClient();

        $scenarioStats = new DashboardStatsService(
            range: StatDateRange::tryFrom($request->get('range', 'last_30_days')),
            client: $client,
            timezone: $request->input('tz', $flow->organization->timezone) ?? 'UTC',
        );

        $scenarioStats->filter(function (Builder $query) use ($flow, $scenarios) {
            return $query
                ->whereIn('a.scenario_id', $scenarios->pluck('id'))
                ->addSelect('a.scenario_id')
                ->groupBy('a.scenario_id');
        });

        $scenarioStats->output(function (Collection $current, Collection $previous) use ($scenarios) {
            return $scenarios
                ->keyBy('id')
                ->map(function (Scenario $scenario) use ($current, $previous) {
                    return [
                        $current->firstWhere('scenario_id', $scenario->id),
                        $previous->firstWhere('scenario_id', $scenario->id),
                    ];
                });
        });
        $scenarioGlobal = $scenarioStats->getScenarioStats();

        $scenarios->each(function (Scenario $scenario) use ($scenarioGlobal) {
            [$current, $previous] = $scenarioGlobal->get($scenario->id);
            $scenario->stats = DashboardStatsService::formatStats($current, $previous);
        });

        return ScenarioApiResource::collection($scenarios);
    }

    public function store(Flow $flow, Request $request)
    {
        $validated = $request->validate([
            'display_name' => [
                'required',
            ],
            'scheme_id' => [

            ],
            'intent' => [
                'required',
                Rule::in([
                    'upgrade',
                    'expansion',
                    'addon',
                    'purchase',
                ]),
            ],
            'lookup_key' => [
                'required',
                'string',
                'max:64',
                Rule::unique('intel_scenarios', 'lookup_key')
                    ->where('organization_id', $request->user()->organization_id),
            ],
            'properties' => [
                'mode' => [
                    'required',
                    Rule::in([
                        'payment',
                        'setup',
                    ]),
                ],
            ],
            // element mode?
            'conditions' => [
                'array',
                new ValidConditionSchema,
            ],
            'element_id' => [
                'required',
                Rule::exists('convert_elements', 'lookup_key')
            ],
            'purchasables' => [
                'array',
                new ValidCheckoutConfig,
            ],
        ]);

        $scenario = new Scenario();
        $scenario->flow_id = $flow->id;
        $scenario->display_name = $validated['display_name'];
        $scenario->intent = $validated['intent'];
        $scenario->lookup_key = $validated['lookup_key'];
        $scenario->conditions = $validated['conditions'];
        $scenario->element_id = Element::retrieve($validated['element_id'])?->id;
        $scenario->organization_id = $flow->organization_id;
        $scenario->properties = Arr::get($validated, 'properties');

        $scenario->save();

        // todo:
        foreach (Arr::get($validated, 'purchasables') ?? [] as $li) {

            $item = match(Arr::get($li, 'object')) {
                'plan' => [
                    'purchasable_type' => 'plan',
                    'purchasable_id' => Plan::retrieve(Arr::get($li, 'id'))?->id,
                ],
                'package' => [
                    'purchasable_type' => 'package',
                    'purchasable_id' => Package::retrieve(Arr::get($li, 'id'))?->id,
                ],
                default => [],
            };

            $scenario->bundleItems()->create($item);
        }

        return new ScenarioApiResource($scenario);
    }
}
