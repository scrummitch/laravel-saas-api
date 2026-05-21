<?php

namespace Tests\Feature;

use App\Jobs\Services\Stripe\ImportStripeProductsJob;
use App\Models\Billing\BillingProvider;
use App\Models\Billing\Charge;
use App\Models\Catalog\Inclusion;
use App\Models\Catalog\Product;
use App\Models\Management\Organization;
use App\Models\Pricing\Plan;
use App\Models\Twin;
use Mockery;
use Money\Currency;
use Money\Money;
use Stripe\Collection;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\StripeClient;
use Tests\TestCase;

class ImportStripeProductsMultiProviderTest extends TestCase
{
    protected $organization;

    protected $testBillingProvider;

    protected $liveBillingProvider;

    protected $stripeMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        // Create test billing provider
        $this->testBillingProvider = BillingProvider::factory()
            ->stripe()
            ->for($this->organization)
            ->create(['name' => 'Test Stripe', 'environment' => 'test']);

        // Pre-load a product and price for the test provider
        $this->preloadProductAndPrice();

        // Create live billing provider
        $this->liveBillingProvider = BillingProvider::factory()
            ->stripe()
            ->for($this->organization)
            ->create(['name' => 'Live Stripe', 'environment' => 'live']);

        $this->stripeMock = Mockery::mock(StripeClient::class);
        $this->stripeMock->products = Mockery::mock();
        $this->stripeMock->prices = Mockery::mock();
    }

    public function testImportProductsAcrossMultipleProviders()
    {
        // Mock Stripe responses for the live provider
        $liveStripeProduct = $this->createMockStripeProduct('prod_live', 'Test Product');
        $liveStripePrice = $this->createMockStripePrice('price_live', 'prod_live');

        // prep search for products
        $this->stripeMock->products->shouldReceive('all')
            ->once()
            ->with(['limit' => 100])
            ->andReturn(Collection::constructFrom([
                'has_more' => false,
                'data' => [$liveStripeProduct],
            ]));

        // prep mock for stripe prices list
        $this->stripeMock->prices->shouldReceive('all')
            ->once()
            ->with([
                'product' => 'prod_live',
                'expand' => [
                    'data.tiers',
                ],
            ])
            ->andReturn([$liveStripePrice]);

        // Run the import job for the live provider
        $job = new ImportStripeProductsJob($this->liveBillingProvider);
        $job->stripeclient = $this->stripeMock;
        $job->handle();

        // Assertions
        $this->assertEquals(1, $this->organization->products()->count(), 'There should be only one product');
        $this->assertEquals(1, $this->organization->charges()->count(), 'There should be only one charge');
        $this->assertEquals(1, $this->organization->plans()->count(), 'There should be only one plan');

        $product = $this->organization->products->first();
        $charge = $this->organization->charges->first();
        $plan = $this->organization->plans->first();

        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals(new Money(1000, new Currency('USD')), $charge->amount);
        $this->assertEquals(new Currency('USD'), $charge->currency);
        $this->assertEquals($product->id, $charge->product_id);
        $this->assertEquals($charge->id, $plan->charges->first()->id);

        // Check twins
        $productTwins = Twin::query()
            ->where('linkable_type', 'product')
            ->where('organization_id', $this->organization->id)
            ->get();
        $this->assertEquals(2, $productTwins->count(), 'There should be two product twins');
        $chargeTwins = Twin::query()->where('linkable_type', 'charge')->where('organization_id', $this->organization->id);
        $this->assertEquals(2, $chargeTwins->count(), 'There should be two charge twins');

        $productTwins = $this->organization
            ->twins()
            ->where('linkable_type', 'product')
            ->get();

        $this->assertCount(2, $productTwins);
        $this->assertEqualsCanonicalizing(
            [$this->testBillingProvider->id, $this->liveBillingProvider->id],
            $productTwins->pluck('connector_id')->toArray()
        );

        $chargeTwins = $this->organization->twins()->where('linkable_type', 'charge')->get();
        $this->assertCount(2, $chargeTwins);
        $this->assertEqualsCanonicalizing(
            [$this->testBillingProvider->id, $this->liveBillingProvider->id],
            $chargeTwins->pluck('connector_id')->toArray()
        );
    }

    protected function preloadProductAndPrice()
    {
        $product = Product::factory()
            ->for($this->organization)
            ->create(['name' => 'Test Product']);
        $charge = Charge::factory()
            ->for($product)
            ->for($this->organization)
            ->create([
                'amount' => 1000,
                'currency' => 'USD',
                'name' => 'price_live',
            ]);
        $plan = Plan::factory()
            ->for($this->organization)
            ->create();

        Inclusion::factory()
            ->for($plan)
            ->for($product)
            ->for($charge, 'charge')
            ->create();

        Twin::factory()->create([
            'organization_id' => $this->organization->id,
            'connector_id' => $this->testBillingProvider->id,
            'connector_type' => 'billing_provider',
            'reference_id' => 'prod_test',
            'type' => StripeProduct::class,
            'linkable_id' => $product->id,
            'linkable_type' => 'product',
            'data' => ['name' => 'Test Product'],
        ]);

        Twin::factory()->create([
            'organization_id' => $this->organization->id,
            'connector_id' => $this->testBillingProvider->id,
            'connector_type' => 'billing_provider',
            'reference_id' => 'price_test',
            'type' => StripePrice::class,
            'linkable_id' => $charge->id,
            'linkable_type' => 'charge',
            'data' => [
                'unit_amount' => 1000,
                'currency' => 'usd',
                'lookup_key' => 'price_live',
            ],
        ]);
    }

    protected function createMockStripeProduct($id = 'prod_123', $name = 'Test Product'): StripeProduct
    {
        return StripeProduct::constructFrom([
            'id' => $id,
            'name' => $name,
            'created' => time(),
        ]);
    }

    protected function createMockStripePrice($id = 'price_123', $productId = 'prod_123'): StripePrice
    {
        return StripePrice::constructFrom([
            'id' => $id,
            'object' => 'price',
            'active' => true,
            'billing_scheme' => 'per_unit',
            'created' => time(),
            'currency' => 'usd',
            'livemode' => false,
            'lookup_key' => 'price_live',
            'product' => $productId,
            'recurring' => [
                'interval' => 'month',
                'interval_count' => 1,
                'usage_type' => 'licensed',
            ],
            'type' => 'recurring',
            'unit_amount' => 1000,
            'unit_amount_decimal' => '1000',
        ]);
    }
}
