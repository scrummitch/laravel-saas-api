<?php

namespace App\Services\Pricing;

use App\Models\Catalog\Product;
use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use App\Models\Pricing\Scheme;
use App\Services\BaseService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdatePricingSchemeService extends BaseService
{
    public function __invoke(Scheme $scheme, array $attributes)
    {
        if (Arr::has($attributes, 'name')) {
            $scheme->name = Arr::get($attributes, 'name');
            $scheme->save();
        }

        if (Arr::has($attributes, 'packages')) {
            $this->updatePackages($scheme, $attributes);
        }

        return $scheme;
    }

    private function updatePackages(Scheme $scheme, array $attributes): void
    {
        $packages = collect(Arr::get($attributes, 'packages'))
            ->map(fn ($id) => Package::retrieve($id))
            ->filter();

        DB::table('pricing_packages')
            ->whereIn('id', $packages->pluck('id'))
            ->update(['pricing_scheme_id' => $scheme->id]);
    }
}
