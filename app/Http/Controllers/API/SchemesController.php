<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSchemeRequest;
use App\Http\Requests\UpdateSchemeRequest;
use App\Http\Resources\Api\SchemeApiResource;
use App\Models\Pricing\Scheme;
use App\Services\Pricing\CreatePricingSchemeService;
use App\Services\Pricing\UpdatePricingSchemeService;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;

class SchemesController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Scheme::class, 'scheme');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $org = auth()->user()->currentOrganization;

        // TODO: check removing pans count

        $query = $org
            ->schemes()
//            ->withCount(['plans'])
            ->with(['plans.charges', 'packages', 'plans.package', 'packages.plans', 'packages.plans.package'])
            ->latest('generated_at');

        return SchemeApiResource::collection($query->paginate());
    }

    public function store(StoreSchemeRequest $request, CreatePricingSchemeService $create)
    {
        $scheme = $create($request->user()->currentOrganization, $request);

        $scheme->refresh();

        $scheme->loadMissing([
            'plans',
            'packages',
        ]);

        return response(new SchemeApiResource($scheme), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Scheme $scheme, Request $request)
    {
        $this->authorize('view', $scheme);

        $scheme
            ->loadMissing(['plans.charges', 'packages', 'plans.package', 'packages.plans', 'packages.plans.package']);

        return new SchemeApiResource($scheme);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSchemeRequest $request, Scheme $scheme, UpdatePricingSchemeService $update)
    {
        $scheme = $update($scheme, $request->validated());

        $scheme->refresh();

        $scheme->loadMissing(['plans', 'plans.charges', 'packages', 'packages', 'packages.plans']);

        return response(new SchemeApiResource($scheme), 201);
    }
}
