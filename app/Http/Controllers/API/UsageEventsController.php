<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Account\Customer;
use App\Models\Usage\UsageEvent;
use App\Validators\UsageEventValidator;
use Illuminate\Http\Request;
use App\Models\Account\Agent;
use App\Models\Account\Association;
use App\Models\Client;
use App\Models\Management\Organization;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class UsageEventsController extends Controller
{
    public function __construct(protected UsageEventValidator $validator)
    {
    }

    public function index(Request $request)
    {
        $org = request()->user()->currentOrganization;

        $query = UsageEvent::query()
            ->where('organization_id', $org->id)
            ->with(['customer'])
            ->latest('id');

        if ($request->filled('customer') && $customer = Customer::retrieve($request->input('customer'))) {
            $query->where('customer_id', $customer->id);
        }

        $results = $query->paginate(25);

        $results->through(function ($item) {

            return [
                ...$item->toArray(),
                'customer' => [
                    'id' => $item->customer->getRouteKey(),
                    'email' => $item->customer->email,
                ],
            ];
        });

        return response()->json($results);
    }

    /**
     * Display the specified resource.
     */
    public function show(UsageEvent $event)
    {
        $this->authorize('view', $event);

        return $event;
    }

    public function store(Request $request)
    {
        $client = $request->user();

        if (!$client instanceof Client) {
            return response()->json([
                'message' => 'unauthorized',
            ], 401);
        }

        $org = $client->organization;

        $customerCache = [];

        $events = $request->filled('events.0')
            ? Arr::wrap($request->input('events', []))
            : [$request->all()];

        $validationResult = $this->validator->validateEvents($events, $org);

        if ($validationResult['has_errors']) {
            $errors = array_merge(
                $validationResult['validation_errors'],
                $validationResult['database_errors']
            );

            return response()->json([
                'message' => 'Validation failed',
                'errors' => $errors
            ], 422);
        }

        $eventsToInsert = [];

        foreach ($events as $event) {
            $uniqueId = $event['id'] ?? Str::uuid()->getHex()->toString();

            $properties = array_merge(
                ['quantity' => $event['quantity'] ?? 1],
                Arr::get($event, 'properties', [])
            );
            $metadata = Arr::get($event, 'metadata');

            $userId = Arr::get($event, 'user');
            $grpId = Arr::get($event, 'group');
            $customerId = Arr::get($event, 'customer');

            $customer = $customerCache[$customerId] ??= Customer::query()
                ->where('reference_id', $customerId)
                ->where('organization_id', $org->id)
                ->firstOrFail();

            $agent = $this->findOrCreateAgent($org, $customer, $userId, $grpId);

            $timestamp = Arr::has($event, 'timestamp')
                ? Carbon::createFromTimestampUTC($event['timestamp'])
                : now();

            $eventsToInsert[] = [
                'organization_id' => $org->id,
                'client_id'       => $client?->id,
                'customer_id'     => $customer->id,
                'agent_id'        => $agent?->id,
                'event_name'      => Arr::get($event, 'event'),
                'unique_id'       => $uniqueId,
                'properties'      => empty($properties) ? NULL : json_encode($properties),
                'metadata'        => empty($metadata) ? NULL : json_encode($metadata),
                'created_at'      => $timestamp
            ];
        }

        $insertedCount = DB::table('usage_events')
            ->upsert(
                $eventsToInsert,
                ['organization_id', 'unique_id'],
                ['event_name', 'properties', "metadata"]
            );

        return response()->json([
            'message' => 'success',
            'events_count' => $insertedCount,
        ]);
    }

    public function destroy(UsageEvent $event)
    {
        $this->authorize('delete', $event);

        $event->delete();

        return response()->noContent();
    }

    private function findOrCreateAgent(Organization $org, ?Customer $customer = null, mixed $userId = null, mixed $grpId = null): ?Agent
    {
        if (empty($userId)) {
            return null;
        }

        $agent = Agent::query()
            ->whereHas('associations', function ($query) use ($customer, $grpId) {
                $query->where('customer_id', $customer->id);
                if ($grpId) {
                    $query->where('key', $grpId);
                }
            })
            ->where('organization_id', $org->id)
            ->where('lookup_key', $userId)
            ->first();

        if (!$agent) {
            $agent = Agent::create([
                'organization_id' => $org->id,
                'lookup_key' => $userId,
            ]);

            Association::create([
                'organization_id' => $org->id,
                'agent_id' => $agent->id,
                'customer_id' => $customer->id,
                'key' => $grpId,
            ]);
        }

        return $agent;
    }
}
