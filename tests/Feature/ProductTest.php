<?php

namespace Tests\Feature;

use App\Models\Billing\BillingProvider;
use App\Models\Catalog\Product;
use App\Models\Management\Organization;
use App\Models\Twin;
use App\Models\Values\ProductStatus;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_create_product_from_stripe(): void
    {
        $org = $this->createOrg('test_create_product_from_stripe');
        /* @var BillingProvider $billing */
        $billing = BillingProvider::factory()
            ->for($org)
            ->stripe()
            ->create();

        /* @var Twin $twin */
        $twin = Twin::factory()
            ->for($billing, 'connector')
            ->for($billing->organization)
            ->stripeProduct()
            ->create();

        /* @var Product $product */
        $product = Product::factory()
            ->for($billing->organization)
            ->create();

        $linked = $product->twins()->save($twin);
        $this->assertSame($twin->id, $linked->id);
        $this->assertCount(1, $product->twins);
        $this->assertNull($product->ancestor);
        $this->assertNull($product->successor);
        $this->assertEmpty($product->descendents);

        $ancestorProduct = Product::factory()
            ->for($billing->organization)
            ->create([
                'name' => 'old product',
            ]);

        $product->ancestor()->associate($ancestorProduct)->save();
        $product = $product->refresh();
        $ancestorProduct = $ancestorProduct->refresh();

        $this->assertNotNull($product->ancestor);
        $this->assertNull($product->successor);
        $this->assertCount(1, $ancestorProduct->descendents);
    }

    public function test_import_products()
    {
        // lines of products from stripe
        $lines = collect([
            [
                'id' => 'prod_'.Str::uuid()->toString(),
                'name' => 'Test Product',
                'active' => true,
                'created' => 1630000000,
            ],
        ]);

        $org = Organization::factory()->create();
        $billing = BillingProvider::factory()->for($org)->stripe()->create();

        // Create the products from the items in "stripe"
        foreach ($lines as $line) {
            /* @var Twin $twin */
            $twin = Twin::factory()
                ->for($billing, 'connector')
                ->for($org)
                ->stripeProduct()
                ->create([
                    'reference_id' => $line['id'],
                    'data' => $line,
                    'reference_created_at' => Carbon::createFromTimestampUTC($line['created']),
                ]);

            $product = Product::factory()
                ->for($org)
                ->create([
                    'name' => $line['name'],
                    'organization_id' => $org->id,
                    'status' => Arr::get($line, 'active') ? ProductStatus::active : ProductStatus::archived,
                    'lookup_key' => Str::slug($line['name'], '_'),
                    'published_at' => Carbon::createFromTimestamp($line['created']),
                ]);
            $product->twins()->save($twin);
        }

        $products = Product::query()
            ->where('organization_id', $org->id)
            ->with('twins')
            ->get();

        $this->assertCount(1, $products);
    }

    public function nameGrouping(array $names, $threshold = 10)
    {
        $groups = [];
        foreach ($names as $name) {
            $foundGroup = false;
            foreach ($groups as $groupKey => $groupNames) {
                foreach ($groupNames as $groupName) {
                    if (levenshtein($name, $groupName) < $threshold) {
                        $groups[$groupKey][] = $name;
                        $foundGroup = true;
                        break;
                    }
                }
                if ($foundGroup) {
                    break;
                }
            }
            if (! $foundGroup) {
                $groups[] = [$name];
            }
        }

        return $groups;
    }
}
