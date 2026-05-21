<?php

namespace App\Jobs\Services\Stripe;

use App\Models\Billing\Charge;
use App\Models\Catalog\Product;
use App\Models\Management\Organization;
use App\Models\Twin;
use App\Services\Sync\Stripe\StripeEventProcessor;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Stripe\Price;
use Stripe\Product as StripeProduct;

class ImportStripeProductsJob extends StripeImportJob
{
    public function handle()
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        logger()->info(logname('start'), [
            'memory_usage' => memory_get_usage(true) / 1024 / 1024 .'MB',
        ]);

        $this->connector->fetchAndUpsert(
            $this->stripeclient->products->all(['limit' => 100]),
            fn (Collection $products) => $this->connector->upsertMany($products, StripeProduct::class)
        );

        Twin::query()
            ->where('connector_id', $this->billing->id)
            ->where('connector_type', 'billing_provider')
            ->where('type', StripeProduct::class)
            ->cursor()
            ->each(fn (Twin $twin) => $this->importProduct($twin));
    }

    protected function importProduct(Twin $productTwin): ?Product
    {
        logger()->info(logname(), [
            'twin' => $productTwin->id,
            'type' => 'product',
            'memory_usage' => memory_get_usage(true) / 1024 / 1024 .'MB',
        ]);

        $product = $this->getOrCreateProduct($productTwin);

        // Import prices for this product
        $stripePrices = $this->stripeclient->prices->all([
            'product' => $productTwin->reference_id,
            'expand' => [
                'data.tiers',
            ],
        ]);

        foreach ($stripePrices as $stripePrice) {
            $priceTwin = $this->createOrUpdateTwin($productTwin->organization, $stripePrice);

            $charge = $this->findOrCreateCharge($product, $priceTwin);

            $priceTwin->link($charge);

            $plan = StripeEventProcessor::ensurePlanIsCreated($charge, $priceTwin, null);
        }

        return $product;
    }

    private function getOrCreateProduct(Twin $productTwin): Product
    {
        if ($productTwin->linkable && $productTwin->linkable instanceof Product) {
            return $productTwin->linkable;
        }

        // find existing product
        $product = Product::query()
            ->where('organization_id', $productTwin->organization_id)
            ->where('name', $productTwin->data['name'])
            ->first();

        if ($product) {
            $productTwin->link($product);

            return $product;
        }

        $product = Product::upsertFromTwin($productTwin);

        $productTwin->link($product);

        return $product;
    }

    private function createOrUpdateTwin(Organization $organization, $stripeObject): Twin
    {
        $twin = Twin::query()
            ->updateOrCreate([
                'organization_id' => $organization->id,
                'connector_id' => $this->billing->id,
                'connector_type' => $this->billing->getMorphClass(),
                'reference_id' => $stripeObject->id,
            ], [
                'type' => get_class($stripeObject),
                'data' => $stripeObject->toArray(),
                'reference_created_at' => Carbon::createFromTimestampUTC($stripeObject->created),
            ]);

        return $twin;
    }

    private function findOrCreateCharge(Product $product, Twin $priceTwin): Charge
    {
        /* @var Price $price */
        $price = $priceTwin->object();

        $currency = new \Money\Currency($price->currency);

        // try and find another twin with the same reference_id
        $altTwin = Twin::query()
            ->where('reference_id', $price->id)
            ->where('organization_id', $product->organization_id)
            ->where('type', Price::class)
            ->first();

        /* @var Charge $charge */
        $charge = value(function () use ($altTwin, $product, $currency, $price) {
            if ($altTwin && $charge = $altTwin->linkable) {
                return $charge;
            }

            // find by name = price.lookup_key, then name = price.id
            foreach (['lookup_key', 'id'] as $key) {
                $charge = Charge::query()
                    ->where('product_id', $product->id)
                    ->where('name', $price->{$key})
                    ->where('currency', $currency->getCode())
                    ->first();

                if (! is_null($charge)) {
                    return $charge;
                }
            }

            return new Charge;
        });

        $charge->organization()->associate($product->organization);
        $charge->product()->associate($product);
        $charge->fillFromTwin($priceTwin);

        $charge->save();

        return $charge;
    }
}
