<?php

namespace App\Services\Pricing;

use App\Http\Requests\StoreSchemeRequest;
use App\Models\Management\Organization;
use App\Models\Pricing\Package;
use App\Models\Pricing\Scheme;
use App\Services\BaseService;
use Illuminate\Support\Arr;

class CreatePricingSchemeService extends BaseService
{
    public function __invoke(Organization $organization, StoreSchemeRequest $request)
    {
        $attributes = $request->validated();
        $scheme = new Scheme;

        $lookupKey = Arr::get($attributes, 'lookup_key');

        $ancestor = Scheme::retrieve(Arr::get($attributes, 'ancestor_id')) ?? Scheme::retrieve($lookupKey);

        $versionNumber = $ancestor ? ($ancestor->version_number ?? 0) + 1 : 1;

        $scheme->version_number = $versionNumber;
        $scheme->version_name = 'v' . $versionNumber;
        $scheme->organization_id = $organization->id;
        $scheme->lookup_key = $lookupKey;
        $scheme->ancestor_id = $ancestor?->id;
        $scheme->name = Arr::get($attributes, 'name');
        $scheme->save();

        foreach ($request->get('packages') ?? [] as $packageId) {
            $package = Package::retrieve($packageId);
            if (is_null($package)) continue;
            $package->pricing_scheme_id = $scheme->id;
            $package->save();
        }


        return $scheme;
    }
}
