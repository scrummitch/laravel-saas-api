<?php

namespace Tests\Feature;

use App\Jobs\Services\Stripe\ImportStripeProductsJob;
use App\Models\Billing\BillingProvider;
use App\Models\Catalog\Product;
use App\Models\Management\Organization;
use App\Models\Twin;
use Mockery;
use Stripe\Collection;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\StripeClient;
use Tests\TestCase;

class ImportStripeProductsJobTest extends TestCase
{
    protected $organization;

    protected $billingProvider;

    protected $stripeMock;

    protected $job;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->billingProvider = BillingProvider::factory()
            ->stripe()
            ->for($this->organization)
            ->create();

        $this->stripeMock = Mockery::mock(StripeClient::class);
        $this->stripeMock->products = Mockery::mock();
        $this->stripeMock->prices = Mockery::mock();

        $this->job = new ImportStripeProductsJob($this->billingProvider);
        $this->job->stripeclient = $this->stripeMock;
    }

    public function testImportProducts()
    {
        $stripeProduct = $this->createMockStripeProduct();
        $stripePrice = $this->createMockStripePrice();

        $this->stripeMock->products->shouldReceive('all')
            ->once()
            ->with(['limit' => 100])
            ->andReturn(Collection::constructFrom([
                'has_more' => false,
                'data' => [$stripeProduct],
            ]));

        $this->stripeMock->prices->shouldReceive('all')
            ->once()
            ->with([
                'product' => 'prod_123',
                'expand' => [
                    'data.tiers',
                ],
            ])
            ->andReturn([$stripePrice]);

        $this->job->handle();

        $this->assertDatabaseHas('twins', [
            'organization_id' => $this->organization->id,
            'connector_id' => $this->billingProvider->id,
            'connector_type' => 'billing_provider',
            'reference_id' => 'prod_123',
            'type' => StripeProduct::class,
        ]);

        $this->assertDatabaseHas('catalog_products', [
            'name' => 'Test Product',
        ]);

        $this->assertDatabaseHas('twins', [
            'organization_id' => $this->organization->id,
            'connector_id' => $this->billingProvider->id,
            'connector_type' => 'billing_provider',
            'reference_id' => 'price_123',
            'type' => StripePrice::class,
        ]);

        $product = $this->organization->products->first();

        $this->assertDatabaseHas('billing_charges', [
            'product_id' => $product->id,
            'amount' => '1000',
            'currency' => 'USD',
        ]);
    }

    public function testImportExistingProduct()
    {
        $existingProduct = Product::factory()
            ->for($this->organization)
            ->create(['name' => 'Existing Product']);

        $existingProductTwin = Twin::factory()->create([
            'organization_id' => $this->organization->id,
            'connector_id' => $this->billingProvider->id,
            'connector_type' => 'billing_provider',
            'reference_id' => 'prod_existing',
            'type' => StripeProduct::class,
            'linkable_id' => $existingProduct->id,
            'linkable_type' => Product::class,
            'data' => ['name' => 'Existing Product'],
        ]);

        $stripeProduct = $this->createMockStripeProduct('prod_existing', 'Existing Product');
        $stripePrice = $this->createMockStripePrice('price_existing', 'prod_existing');

        $this->stripeMock->products->shouldReceive('all')
            ->once()
            ->with(['limit' => 100])
            ->andReturn(Collection::constructFrom(['has_more' => false, 'data' => [$stripeProduct]]));

        $this->stripeMock->prices->shouldReceive('all')
            ->once()
            ->with([
                'product' => 'prod_existing',
                'expand' => [
                    'data.tiers',
                ],
            ])
            ->andReturn([$stripePrice]);

        $this->job->handle();

        $this->assertDatabaseHas('catalog_products', [
            'id' => $existingProduct->id,
            'name' => 'Existing Product',
        ]);

        $this->assertDatabaseHas('billing_charges', [
            'product_id' => $existingProduct->id,
            'amount' => '1000',
            'currency' => 'USD',
        ]);

        // see there is ONE charge in the org
        $this->assertEquals(1, $this->organization->charges()->count());
        // see there is ONE product in the org
        $this->assertEquals(1, $this->organization->products()->count());
        // see there is ONE plan
        $this->assertEquals(1, $this->organization->plans()->count());
    }

    protected function createMockStripeProduct($id = 'prod_123', $name = 'Test Product'): StripeProduct
    {
        $product = new StripeProduct($id);
        $product->name = $name;
        $product->descrpition = 'Test Description';
        $product->active = true;
        $product->created = time();

        return $product;
    }

    protected function createMockStripePrice($id = 'price_123', $productId = 'prod_123'): StripePrice
    {
        $priceData = [
            'id' => $id,
            'object' => 'price',
            'active' => true,
            'billing_scheme' => 'per_unit',
            'created' => time(),
            'currency' => 'usd',
            'livemode' => false,
            'lookup_key' => null,
            'metadata' => [],
            'nickname' => null,
            'product' => $productId,
            'recurring' => [
                'aggregate_usage' => null,
                'interval' => 'month',
                'interval_count' => 1,
                'usage_type' => 'licensed',
            ],
            'tax_behavior' => 'exclusive',
            'tiers_mode' => null,
            'transform_quantity' => null,
            'type' => 'recurring',
            'unit_amount' => 1000,
            'unit_amount_decimal' => '1000',
        ];

        return StripePrice::constructFrom($priceData);
    }
}
