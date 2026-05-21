<?php

namespace App\Jobs;

use App\Billing\Currency;
use App\Billing\ISO4217;
use App\ML\DBSCAN;
use App\Models\Billing\BillingProvider;
use App\Models\Catalog\Product;
use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use App\Models\Pricing\Scheme;
use App\Services\Billing\CreateSandboxCouponService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConfigureInitialPricingSchemeJob implements ShouldQueue
{
    use Batchable,
        Dispatchable,
        InteractsWithQueue,
        Queueable,
        SerializesModels;

    public function __construct(public BillingProvider $billing) {}

    public function handle(): void
    {
        $org = $this->billing->organization;

        CreateSandboxCouponService::dispatch($this->billing);

        $plans = $org
            ->plans()
            ->with(['charges', 'charges.product'])
            ->get();

        $plansByProduct = $plans
            ->groupBy(function (Plan $plan) {
                return $plan->charges->first()?->product_id;
            });

        $products = $org
            ->products()
            ->whereIn('id', $plansByProduct->keys()->toArray())
            ->get();

        $clusters = $this->calculateClusters($products);

        foreach ($clusters as $i => $group) {
            $productsInRange = $products->whereIn('id', $group);

            $name = $i === 0 ? 'First Pricing Scheme' : $this->dateRangeName($productsInRange);

            Scheme::unguard();
            /* @var Scheme $scheme */
            $scheme = Scheme::query()
                ->firstOrCreate([
                    'organization_id' => $org->id,
                    'lookup_key' => Str::slug($name, '_'),
                ], [
                    'version_number' => 1,
                    'version_name' => 'v1',
                    'name' => $name,
                    'generated_at' => $productsInRange
                        ->sort(fn (Product $a, Product $b) => $a->published_at <=> $b->published_at)
                        ->first()
                        ?->published_at,
                ]);
            Scheme::reguard();

            $plansKeyedByProductId = $plansByProduct
                ->filter(function ($plans, $product_id) use ($productsInRange) {
                    return $productsInRange->contains('id', $product_id);
                });

            Package::unguard();
            $plansKeyedByProductId
                ->each(function (Collection $plans, int $productId) use ($products, $scheme) {
                    $product = $products->firstWhere('id', $productId);

                    $package = Package::query()
                        ->firstOrCreate([
                            'pricing_scheme_id' => $scheme->id,
                            'lookup_key' => $product->lookup_key,
                        ], [
                            'name' => $product->name,
                            'organization_id' => $this->billing->organization_id,
                        ]);

                    return $plans->each(function (Plan $plan) use ($package) {
                        $plan->package_id = $package->id;
                        $plan->save();
                    });
                })
                ->values()
                ->toArray();
            Package::reguard();
        }

        // list all charges for the org, grouped by currency and pick the one with the most instances
        $currency = $org
            ->charges()
            ->selectRaw('currency, count(*) as count')
            ->groupBy('currency')
            ->orderByDesc('count')
            ->first()
            ?->currency ?? 'USD';

        if (is_null($org->default_currency)) {
            $org->default_currency = ISO4217::make($currency);
            $org->save();
        }
    }

    private function dateRangeName(Collection $products): string
    {
        $months = $products
            ->map(fn (Product $product) => $product->published_at->format('M Y'))
            ->unique();

        if ($months->count() === 1) {
            return $months->first().' Pricing Scheme';
        }

        return $months->first().' - '.$months->last().' Pricing Scheme';
    }

    private function calculateClusters(\Illuminate\Database\Eloquent\Collection $products): array
    {
        $points = [];
        $distance_matrix = [];

        foreach ($products as $product_i) {
            /* @var Product $product_i */
            $points[] = $product_i->id;
            $date_i = $product_i->published_at;

            foreach ($products as $product_j) {
                /* @var Product $product_j */
                $date_j = $product_j->published_at;
                if (is_null($date_i)) {
                    continue;
                }
                $distance_matrix[$product_i->id][$product_j->id] = $date_i->diffInDays($date_j);
            }
        }

        $dbscan = new DBSCAN($distance_matrix, $points);

        return $dbscan->dbscan(30, 0);
    }
}
