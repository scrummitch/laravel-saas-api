<?php

namespace App\Jobs\Services\Stripe;

use App\Models\Catalog\Product;
use App\Models\Twin;
use App\Models\Values\PlanType;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Stripe\Price;

/**
 * @deprecated
 */
class ImportStripePricesJob extends StripeImportJob
{
    protected Collection $products;

    public function handle()
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if (is_null($this->billing)) {
            $this->fail('Service connection not found');
        }

        $this->connector->fetchAndUpsert(
            $this->stripeclient->prices->all([
                'limit' => 100,
                'expand' => [
                    'data.tiers',
                ],
            ]),
            fn (Collection $products) => $this->connector->upsertMany($products, Price::class)
        );

        $this->products = Product::query()
            ->where('organization_id', $this->billing->organization_id)
            ->get();

        Twin::query()
            ->where('connector_id', $this->billing->id)
            ->where('type', Price::class)
            ->cursor()
            ->each(fn (Twin $twin) => $this->importPrice($twin));
    }

    protected function importPrice(Twin $twin)
    {
        logger()->info(logname(), ['twins' => $twin->id]);

    }

    public static function determineInterval(Price $price): CarbonInterval
    {
        if (isset($price->recurring)) {
            $interval = new CarbonInterval(null);
            $interval->add($price->recurring->interval, $price->recurring->interval_count);

            return $interval;
        }

        return CarbonInterval::create('P1M');
    }

    public static function englishInterval(Price $price): string
    {
        $interval = isset($price->recurring) ? $price->recurring->interval : 'day';
        $count = isset($price->recurring) ? $price->recurring->interval_count : 1;

        $interval = match ($interval) {
            'day' => 'day',
            'week' => 'week',
            'month' => 'month',
            'year' => 'year',
            default => $interval,
        };

        return $count === 1 ? $interval : $count.' '.Str::plural($interval, $count);
    }

    public static function determinePlanType(Price $price, Product $product): PlanType
    {
        if (Str::contains($product->name, ['addon', 'add on', 'add-on'])) {
            return PlanType::addon;
        }

        if (Str::contains($product->name, ['free']) && $price->unit_amount === 0) {
            return PlanType::provisional;
        }

        if ($price->type === 'one_time') {
            return PlanType::custom;
        }

        return PlanType::standard;
    }
}
