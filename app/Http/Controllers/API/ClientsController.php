<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\Api\ClientApiResource;
use App\Jobs\UpdateClientExclusionsJob;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ClientsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $org = $request->user()->currentOrganization;

        $clients = $org
            ->clients()
            ->with(['billingProvider', 'tokens'])
            ->paginate();

        return ClientApiResource::collection($clients);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClientRequest $request)
    {
        $validated = $request->validated();

        $client = new Client;

        $client->name = $request->get('name');
        $client->type = $request->get('type');
        $client->platform = $request->get('platform');
        $client->organization_id = $request->get('organization_id');

        if (Arr::has($validated, 'billing_provider_id')) {
            $client->billing_provider_id = $request->get('billing_provider_id');
        }

        $client->regenerateSecret();

        $request
            ->user()
            ->currentOrganization
            ->clients()
            ->save($client);

        return new ClientApiResource($client);
    }

    public function secret(Client $client)
    {
        $this->authorize('view', $client);

        return response()->json([
            'secret' => $client->secret,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Client $client)
    {
        $client->loadMissing(['billingProvider']);

        return new ClientApiResource($client);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClientRequest $request, Client $client)
    {
        $this->authorize('update', $client);

        $validated = $request->validated();

        if (Arr::has($validated, 'billing_provider_id')) {
            $client->billing_provider_id = Arr::get($validated, 'billing_provider_id');
        }

        if (Arr::has($validated, 'exclusion_rules')) {
            $client->exclusion_rules = Arr::get($validated, 'exclusion_rules');
        }

        if (Arr::has($validated, 'allowed_origins')) {
            $client->allowed_origins = Arr::get($validated, 'allowed_origins');
        }

        // remove any values from pending_origins that are in allowed_origins
        $client->pending_origins = collect($client->pending_origins)
            ->filter(fn ($origin) => ! in_array($origin, $client->allowed_origins->toArray()))
            ->values();

        if ($client->isDirty('exclusion_rules')) {
            UpdateClientExclusionsJob::dispatch($client);
        }

        $client->name = $request->get('name');
        $client->save();

        $client->loadMissing(['billingProvider']);

        return new ClientApiResource($client);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        $this->authorize('delete', $client);

        $client->delete();

        return response()->noContent();
    }
}
