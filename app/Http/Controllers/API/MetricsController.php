<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMetricRequest;
use App\Http\Requests\UpdateMetricRequest;
use App\Http\Resources\Api\MetricApiResource;
use App\Models\Usage\Metric;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MetricsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $org = $request->user()->currentOrganization;

        $query = $org
            ->metrics()
            ->with('feature');

        return MetricApiResource::collection($query->paginate());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMetricRequest $request)
    {
        $validated = $request->validated();

        $metric = new Metric;
        $metric->organization_id = $request->user()->currentOrganization?->id;
        $metric->feature_id = Arr::get($validated, 'feature_id');
        $metric->aggregation = Arr::get($validated, 'aggregation');
        $metric->event_name = Arr::get($validated, 'event_name');
        $metric->type = Arr::get($validated, 'type', 'persistent');

        $metric->save();

        return new MetricApiResource($metric);
    }

    /**
     * Display the specified resource.
     */
    public function show(Metric $metric)
    {
        $this->authorize('view', $metric);
        $metric->loadMissing('feature');

        return new MetricApiResource($metric);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMetricRequest $request, Metric $metric)
    {
        $validated = array_filter($request->validated());

        Metric::unguard();
        $metric->fill($validated);
        Metric::reguard();

        $metric->save();

        return new MetricApiResource($metric);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Metric $metric)
    {
        $this->authorize('delete', $metric);

        $metric->delete();

        return response()->noContent();
    }
}
