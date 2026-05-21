<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageApiResource;
use App\Models\Pricing\Package;
use App\Models\Pricing\Scheme;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PackagesController extends Controller
{
    public function index(Request $request)
    {
        $org = $request->user()->currentOrganization;

        $query = $org
            ->packages()
            ->with(['plans', 'plans.charges', 'scheme'])
            ->latest();

        return PackageApiResource::collection($query->paginate());
    }

    public function show(Package $package)
    {
        $package->loadMissing(['plans', 'plans.charges']);

        return new PackageApiResource($package);
    }

    public function update(Package $package, Request $request)
    {
        $request->merge([
            'pricing_scheme_id' => Scheme::retrieve($request->pricing_scheme_id)->id
        ]);

        $validated = $request->validate([
            'name' => 'required|string',
            'pricing_scheme_id' => [
                'required',
                Rule::exists('pricing_schemes', 'id')
                    ->where('organization_id', auth()->user()->currentOrganization->id)
            ]
        ]);
        Package::unguarded(fn () => $package->update($validated));

        return new PackageApiResource($package);
    }
}
