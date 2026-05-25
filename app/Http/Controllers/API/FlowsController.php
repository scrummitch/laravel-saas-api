<?php

namespace App\Http\Controllers\API;

use App\Database\DeviceDetectorCache;
use App\Http\Concerns\ResolvesPerPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkflowRequest;
use App\Http\Requests\UpdateWorkflowRequest;
use App\Http\Resources\Api\ActivityApiResource;
use App\Http\Resources\Api\SignalApiResource;
use App\Http\Resources\Api\WorkflowApiResource;
use App\Models\Client;
use App\Models\Convert\BundleItem;
use App\Models\Convert\Flow;
use App\Models\Convert\Handler;
use App\Models\Intelligence\Activity;
use App\Models\Intelligence\ActivityAction;
use App\Models\Media\Asset;
use App\Models\Pricing\Plan;
use App\Models\Stats\Collector;
use App\Models\Store\PurchaseItem;
use App\Services\Analytics\DashboardStatsService;
use App\Services\Analytics\StatDateRange;
use Carbon\Carbon;
use DeviceDetector\DeviceDetector;
use Firebase\JWT\JWT;
use GeoIp2\Database\Reader;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FlowsController extends Controller
{
    use ResolvesPerPage;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Flow::class);

        $org = auth()->user()->currentOrganization;

        $query = $org
            ->workflows()
            ->with(['scenarios', 'images', 'scenarios.bundleItems', 'scenarios.bundleItems.purchasable'])
            ->withCount(['scenarios'])
            ->latest();

        $stats = new DashboardStatsService(
            range: StatDateRange::all_time,
            client: $org->liveClient(),
            timezone: $org->timezone,
        );

        $stats->filter(function (Builder $query) use ($org) {
            return $query
                ->addSelect('s.flow_id')
                ->groupBy('s.flow_id');
        });

        $stats = $stats->output(function ($now, $later) {
            return collect($now);
        });

        $workflows = $query->paginate($this->perPage($request));
        $workflowStats = collect($stats->getGlobalStats());

        $workflows->each(function ($workflow) use ($workflowStats) {
            $workflow->stats = $workflowStats
                ->where('flow_id', $workflow->id)
                ->first() ?? null;
        });

        return WorkflowApiResource::collection($workflows);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreWorkflowRequest $request)
    {
        $this->authorize('create', Flow::class);

        $validated = $request->validated();
        $workflow = new Flow;

        $workflow->name = Arr::get($validated, 'name');
        $workflow->lookup_key = Arr::get($validated, 'lookup_key');
        $workflow->organization_id = $request->user()->organization_id;

        $workflow->save();

        foreach (Arr::get($validated, 'handlers', []) as $h) {
            $handler = new Handler();
            $handler->uuid = Str::uuid()->toString();
            $handler->flow_id = $workflow->id;
            $handler->listen = Arr::get($h, 'listen');
            $handler->event_name = Arr::get($h, 'event_name');
            $handler->qualifier = Arr::get($h, 'qualifier', 'none');
            $handler->props = Arr::get($h, 'props');

            $handler->save();
        }

        return new WorkflowApiResource($workflow);
    }

    /**
     * Display the specified resource.
     */
    public function show(Flow $flow)
    {
        $this->authorize('view', $flow);

        $flow
            ->loadMissing([
                'scenarios', 'images', 'scenarios.bundleItems', 'scenarios.bundleItems.purchasable'
            ]);

        return new WorkflowApiResource($flow);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateWorkflowRequest $request, Flow $flow)
    {
        $this->authorize('update', $flow);

        $validated = $request->validated();

        $flow->name = Arr::get($validated, 'name');
        $flow->lookup_key = Str::slug($flow->name);
//        $flow->triggers = Arr::get($validated, 'triggers');

        $images = collect($request->get('images', []))
            ->map(fn ($image) => Asset::retrieve($image))
            ->filter()
            ->values();

        if (! empty($images)) {
            $flow->syncAttachments('image', $images);
        }
        $handlers = $flow->handlers;

        foreach (Arr::get($validated, 'handlers', []) as $h) {

            $handler = $handlers->where('uuid', Arr::get($h, 'id'))->first();
            if (!$handler) {
                $handler = new Handler();
                $handler->uuid = Str::uuid()->toString();
                $handler->flow_id = $flow->id;
            }
            $handler->listen = Arr::get($h, 'listen');
            $handler->event_name = Arr::get($h, 'event_name');
            $handler->qualifier = Arr::get($h, 'qualifier', 'none');
            $handler->props = Arr::get($h, 'props');

            if ($handler->isDirty()) {
                $handler->save();
            }
        }

        $flow->save();

        $flow->loadMissing(['images']);

        return new WorkflowApiResource($flow);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Flow $flow)
    {
        $this->authorize('delete', $flow);

        $flow->delete();

        return response()->noContent();
    }

    public function resultsIndex(Flow $flow, Request $request)
    {
        $this->authorize('view', $flow);

        $org = $request->user()->currentOrganization;
        $client = Client::retrieve($request->get('client')) ?? $org->liveClient();

        $stats = new DashboardStatsService(
            range: StatDateRange::tryFrom($request->get('range', 'last_30_days')),
            client: $client,
            timezone: $request->input('tz', $org->timezone) ?? 'UTC',
        );

        $stats->filter(function (Builder $query) use ($flow) {
            return $query->whereIn('s.id', $flow->scenarios->pluck('id'));
        });

        return [
            'global_stats' => $stats->getGlobalStats(),
            'chart_data' => $stats->getActivityTimeSeries(),
        ];
    }

    public function storeSandboxAgent(Flow $flow, Request $request)
    {
        $this->authorize('update', $flow);

        $benchmarks = [];
        $startTime = microtime(true);

        // Benchmark: Get client
        $client = $flow
            ->clients()
            ->where('environment', 'test')
            ->first();
        $benchmarks['get_client'] = microtime(true) - $startTime;

        // Benchmark: Generate JWT
        $jwtStart = microtime(true);
        $jwt = JWT::encode([
            'sub' => $uid = 'user_'.Str::ulid()->toBase58(),
            'exp' => Carbon::now()->addDay()->timestamp,
            'aud' => 'sandbox',
            'email' => $uid.'@sandbox.plandalf.com',
        ], $client->getSecretStr(), 'HS256', $client->getRouteKey());
        $benchmarks['generate_jwt'] = microtime(true) - $jwtStart;

        // Benchmark: GeoIP lookup
        $geoStart = microtime(true);
        $cityDbReader = new Reader(resource_path('app/GeoLite2-Country.mmdb'));
        try {
            $record = $cityDbReader->country($request->ip());
        } catch (\Throwable $e) {
            $record = null;
        }
        $benchmarks['geoip_lookup'] = microtime(true) - $geoStart;

        // Benchmark: Device detection
        $ddStart = microtime(true);
        $dd = new DeviceDetector($request->userAgent());
        $dd->setCache(new DeviceDetectorCache);
        $dd->skipBotDetection();
        $dd->parse();
        $benchmarks['device_detection'] = microtime(true) - $ddStart;

        // Benchmark: Create collector
        $collectorStart = microtime(true);
        $v = [
            'client_id' => $client->id,
            'agent_id' => null,
            'origin' => config('app.url'),
            'country' => $record?->country->isoCode,
            'os' => Arr::get($dd->getOs(), 'short_name'),
            'browser' => Arr::get($dd->getClient(), 'short_name'),
            'created_at' => now(),
        ];
        $collector = Collector::query()->create($v);
        $benchmarks['create_collector'] = microtime(true) - $collectorStart;

        // Calculate total execution time
        $totalTime = microtime(true) - $startTime;
        $benchmarks['total'] = $totalTime;

        // Log benchmarks
        Log::info('storeSandboxAgent benchmarks', $benchmarks);

        return response()->json([
            'token' => $jwt,
            'collector' => $collector->getRouteKey(),
        ]);
    }

    public function conversionsIndex(Flow $flow)
    {
        $this->authorize('view', $flow);

        $flow->loadMissing(['scenarios']);

        $query = Activity::query()
            ->with(['purchase', 'purchase.items', 'attribution', 'customer', 'collector', 'handler', 'scenario'])
            ->where('has_completed', true)
            ->whereHas('scenario', function ($query) use ($flow) {
                $query->where('flow_id', $flow->id);
            });

        $data = $query
            ->latest('created_at')
            ->simplePaginate(10)
            ->through(function (Activity $session) {
                return [
                    'id' => $session->getRouteKey(),

                    'customer_id' => $session->customer?->getRouteKey(),
                    'customer_email' => $session->customer?->email ?? $session->collector->customer?->email ?? null,
                    'customer_created_at' => $session->collector->customer?->reference_created_at->timestamp ?? null,

                    'placement' => $session->handler?->listen,
                    'variant_name' => $session->scenario?->display_name,

                    'attribution' => $session->attribution ? [
                        'id' => $session->attribution->id,
                        'proceeds_amount_gross' => $session->attribution->proceeds_amount_gross->getAmount(),
                    ] : null,

                    'items' => $session->purchase?->items->map(function (PurchaseItem $item) {
                        return [
                            'id' => $item->id,
                            'purchasable_id' => $item->purchasable->getPurchasableId(),
                            'purchasable_type' => $item->purchasable->getType(),
                        ];
                            $charge = $purchasable->getCharges()->first();

                            return [
                                'id' => $purchasable->getPurchasableId(),
                                'object' => $purchasable->getType(),
                                'name' => $purchasable->getDisplayName(),
                                'currency_code' => $purchasable->getCurrency()->getCode(),
                                'amount_total' => $charge->amount->getAmount(),
                                'amount_total_formatted' => ChargeFormatter::format($charge->amount),
                                // transaction url in stripe/
                            ];
                        }) ?? [],
//                        ?? $session->scenario->purchasables->map(function (BundleItem $purchasable) {
//                            $charge = $purchasable->getCharges()->first();
//
//                            return [
//                                'id' => $purchasable->getPurchasableId(),
//                                'object' => $purchasable->getType(),
//                                'name' => $purchasable->getDisplayName(),
//                                'currency_code' => $purchasable->getCurrency()->getCode(),
//                                'amount_total' => $charge->amount->getAmount(),
//                                'amount_total_formatted' => ChargeFormatter::format($charge->amount),
//                                // transaction url in stripe/
//                            ];
//                        }),
                    'completed_at' => $session->finished_at?->timestamp,

//                    'is_excluded' => $session->is_excluded,
//                    'exclusion_reason' => $session->exclusion_reason,
                ];
            });

        return $data;
    }

    public function eventsIndex(Flow $flow)
    {
        $this->authorize('view', $flow);

        $events = ActivityAction::query()
            ->with(['activity'])
            ->whereHas('activity', function ($query) use ($flow) {
                $query->whereHas('scenario', function ($query) use ($flow) {
                    $query->where('flow_id', $flow->id);
                });
            })
            ->latest();

        $paginated = $events->simplePaginate();

        return [
            'data' => collect($paginated->items())->map(function (ActivityAction $action) {
                return [
                    'id' => $action->id,
                    'collector_id' => $action->activity->id,
                    'data' => $action->properties,
                    'type' => $action->type->getKey(),
                    'created_at' => $action->created_at->timestamp,
                ];
            }),
            'links' => [],
            'meta' => []
        ];
    }

    public function sessionsIndex(Flow $flow)
    {
        $this->authorize('view', $flow);

        $query = $flow
            ->activities()
            ->latest('id')
            ->with([
                'scenario',
                'flow',
                'actions',
                'customer',
                'collector',
                'collector.agent',
                'customer.twin',
                'customer.twin.connector'
            ])
            ->withCount(['views']);

        return ActivityApiResource::collection($query->simplePaginate());
    }
}
