<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFeatureRequest;
use App\Http\Requests\UpdateFeatureRequest;
use App\Http\Resources\Api\FeatureResource;
use App\Models\Catalog\Feature;
use App\Models\Usage\Metric;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class FeaturesController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Feature::class, 'feature');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $org = auth()->user()->currentOrganization;

        $query = $org
            ->features()
            ->with(['metrics', 'featureSet'])
            ->latest();

        return FeatureResource::collection($query->simplePaginate());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFeatureRequest $request)
    {
        $validated = $request->validated();

        $feature = new Feature;
        $feature->organization_id = auth()->user()->currentOrganization->id;
        $feature->fill(Arr::only($validated, ['lookup_key', 'name', 'released_at']));
        $feature->save();

        if (isset($validated['metric'])) {
            $metric = new Metric;
            $metric->organization_id = auth()->user()->currentOrganization->id;
            $metric->feature_id = $feature->id;
            $metric->fill(Arr::only($validated['metric'], [
                'event_name',
                'aggregation',
                'type',
                'field_name',
            ]));
            $metric->save();
        }

        return new FeatureResource($feature);
    }

    /**
     * Display the specified resource.
     */
    public function show(Feature $feature)
    {
        $this->authorize('view', $feature);
        $feature->loadMissing(['metrics', 'featureSet']);

        return new FeatureResource($feature);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFeatureRequest $request, Feature $feature)
    {
        $validated = $request->validated();

        Feature::unguard();
        $feature->fill(Arr::only($validated, ['lookup_key', 'name', 'feature_set_id']));
        $feature->save();

        $feature->loadMissing(['metrics', 'featureSet']);

        return new FeatureResource($feature);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Feature $feature)
    {
        $this->authorize('delete', $feature);
        $feature->delete();

        return response()->noContent();
    }
}
