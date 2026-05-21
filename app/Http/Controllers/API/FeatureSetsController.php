<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\FeatureSetApiResource;
use App\Models\Catalog\FeatureSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FeatureSetsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', FeatureSet::class);

        $org = auth()->user()->currentOrganization;

        $query = $org
            ->featureSets()
            ->with(['features'])
            ->latest();

        return FeatureSetApiResource::collection($query->simplePaginate());
    }

    public function store(Request $request)
    {
        $this->authorize('create', FeatureSet::class);

        $validated = $request->validate([
            'name' => 'required|string',
        ]);

        $org = auth()->user()->currentOrganization;

        $featureSet = $org->featureSets()->create($validated + [
            'key' => Str::slug($validated['name']),
        ]);

        return new FeatureSetApiResource($featureSet);
    }

    public function show(FeatureSet $featureSet)
    {
        $this->authorize('view', $featureSet);

        $featureSet->load(['features']);

        return new FeatureSetApiResource($featureSet);
    }

    public function update(FeatureSet $featureSet, Request $request)
    {
        $this->authorize('update', $featureSet);

        $validated = $request->validate([
            'name' => 'required|string',
            'description' => [
                'nullable',
                'string',
            ],
        ]);

        $featureSet->name = $validated['name'];
        $featureSet->description = $validated['description'];
        $featureSet->save();

        return new FeatureSetApiResource($featureSet);
    }

    public function destroy(FeatureSet $featureSet)
    {
        $this->authorize('delete', $featureSet);

        DB::table('catalog_features')
            ->where('feature_set_id', $featureSet->id)
            ->where('organization_id', $featureSet->organization_id)
            ->update(['feature_set_id' => null]);

        $featureSet->delete();

        return response()->noContent();
    }
}
