<?php

namespace Tests\Feature;

use App\Jobs\ConfigureInitialPricingSchemeJob;
use App\Jobs\Services\Stripe\ImportStripeProductsJob;
use App\Models\Billing\BillingProvider;
use App\Models\Billing\Charge;
use App\Models\Catalog\Product;
use App\Models\Pricing\Plan;
use App\Models\Pricing\Scheme;
use App\Models\Twin;
use App\Services\Billing\CreateSandboxCouponService;
use Mockery;
use Stripe\Collection;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Stripe\Service\PriceService;
use Stripe\Service\ProductService;
use Stripe\StripeClient;
use Tests\TestCase;

class ImportMultiStripeTest extends TestCase
{
    public function test_import_two_stripe_accounts()
    {
        $org = $this->createOrg();

        // Create test and live BillingProvider instances
        $testProvider = BillingProvider::factory()
            ->stripe()
            ->for($org)
            ->create(['environment' => 'test']);
        $liveProvider = BillingProvider::factory()
            ->stripe()
            ->for($org)
            ->create(['environment' => 'live']);

        $org->live_billing_provider_id = $liveProvider->id;
        $org->test_billing_provider_id = $testProvider->id;
        $org->save();

        // Mock Stripe clients for test and live environments
        $testStripeClient = $this->mockStripeClient('test');
        $liveStripeClient = $this->mockStripeClient('live');

        // Run import jobs for both providers
        $testJob = new ImportStripeProductsJob($testProvider);
        $testJob->stripeclient = $testStripeClient;
        $testJob->handle();

        // intercept app()->make() for CreateSandboxCouponService and return a mock which expects a call to dispatch()
        $this->mock(CreateSandboxCouponService::class, function ($mock) {
            $mock->shouldReceive('__invoke')->twice();
        });

        $configureTest = new ConfigureInitialPricingSchemeJob($testProvider);
        $configureTest->handle();

        $liveJob = new ImportStripeProductsJob($liveProvider);
        $liveJob->stripeclient = $liveStripeClient;
        $liveJob->handle();

        $configureLive = new ConfigureInitialPricingSchemeJob($liveProvider);
        $configureLive->handle();

        // Assertions
        $this->assertEquals(1, Product::query()->where('organization_id', $org->id)->count(), 'There should be two products');
        $product = Product::query()->where('organization_id', $org->id)->first();
        // each product (just the 1) needs 2 charges
        $this->assertSame(2, $product->charges()->count(), 'There should be two charges');

        $this->assertEquals(2, Charge::query()->where('organization_id', $org->id)->count(), 'There should be four charges');
        foreach (Charge::query()->where('organization_id', $org->id)->get() as $charge) {
            $this->assertSame(2, Twin::query()->where('linkable_id', $charge->id)->where('linkable_type', 'charge')->count(), 'Each charge should have two twins');
        }
        $this->assertEquals(2, Plan::query()->where('organization_id', $org->id)->count(), 'There should be four plans');
        foreach (Plan::query()->where('organization_id', $org->id)->get() as $plan) {
            $this->assertSame(1, $plan->charges()->count(), 'Each plan should have one charge');
        }

        $this->assertProductAndCharges($org, 'Product 1', $testProvider);
        $this->assertProductAndCharges($org, 'Product 1', $liveProvider);

        $this->assertPlans($org);

        $schemes = Scheme::query()
            ->where('organization_id', $org->id)
            ->get();
        $this->assertCount(1, $schemes);
        $this->assertCount(1, $schemes->get(0)->packages);
        $package = $schemes->get(0)->packages->first();
        $this->assertCount(2, $package->plans);
    }

    private function mockStripeClient($environment)
    {
        $stripeClient = Mockery::mock(StripeClient::class);
        $productService = Mockery::mock(ProductService::class);
        $priceService = Mockery::mock(PriceService::class);

        $stripeClient->products = $productService;
        $stripeClient->prices = $priceService;

        $productService
            ->shouldReceive('all')
            ->once()
            ->with(['limit' => 100])
            ->andReturn($this->mockStripeProducts($environment));

        $priceService->shouldReceive('all')
            ->once()
            ->with([
                'product' => "prod_{$environment}1",
                'expand' => [
                    'data.tiers',
                ],
            ])
            ->andReturn($this->mockStripePrices($environment));

        return $stripeClient;
    }

    private function assertProductAndCharges($org, $productName, $provider)
    {
        /* @var Product $product */
        $product = Product::where('organization_id', $org->id)
            ->where('name', $productName)
            ->first();

        $this->assertNotNull($product);
        $this->assertEquals(2, $product->charges()->count());
        $this->assertEquals($productName, $product->name);
        $this->assertEquals('active', $product->status->value);
        $this->assertNotNull($product->lookup_key);
        $this->assertNotNull($product->description);
        $this->assertNotNull($product->published_at);
        $this->assertEquals(1, $product->version_number);
        // $this->assertEquals('v1', $product->version_name);

        $charges = $product->charges;
        $this->assertCount(2, $charges);
        foreach ($charges as $charge) {
            $this->assertContains($charge->amount->getAmount(), ['1000', '2000']);
            $this->assertEquals('USD', $charge->currency->getCode());
            //            $this->assertContains($charge->properties['recurring']['interval'], ['month', 'year']);
            //            $this->assertEquals($provider->id, $charge->organization->billing_provider_id);
            $this->assertEquals('standard', $charge->type);
            $this->assertEquals('in_advance', $charge->mode);
        }
    }

    private function assertPlans($org)
    {
        $plans = Plan::where('organization_id', $org->id)->get();

        // Assert there are 4 plans
        $this->assertCount(2, $plans);

        foreach ($plans as $plan) {
            /* @var Plan $plan */
            $plan->loadMissing(['charges']);

            // Assert that each plan has exactly one charge
            $this->assertCount(1, $plan->charges, 'Plan should have exactly one charge');

            $charge = $plan->charges->first();
            $this->assertNotNull($charge, 'Plan should have a charge');

            // Compare plan attributes with its associated charge
            //            $this->assertEquals($plan->amount->getAmount(), $charge->amount->getAmount(), "Plan amount should match charge amount");
            $this->assertEquals($plan->currency->getCode(), $charge->currency->getCode(), 'Plan currency should match charge currency');

            // Assuming the interval is stored in the charge's properties
            //            $this->assertEquals($plan->interval, $charge->properties['recurring']['interval'], "Plan interval should match charge interval");
        }
    }

    private function mockStripeProducts($environment)
    {
        return Collection::constructFrom([
            'object' => 'list',
            'data' => [
                StripeProduct::constructFrom([
                    'id' => "prod_{$environment}1",
                    'object' => 'product',
                    'active' => true,
                    'attributes' => [],
                    'created' => time(),
                    'default_price' => null,
                    'description' => "Description for {$environment} product 1",
                    'images' => [],
                    'livemode' => $environment === 'live',
                    'metadata' => [],
                    'name' => 'Product 1',
                    'package_dimensions' => null,
                    'shippable' => null,
                    'statement_descriptor' => null,
                    'tax_code' => null,
                    'type' => 'service',
                    'unit_label' => null,
                    'updated' => time(),
                    'url' => null,
                ]),
            ],
            'has_more' => false,
            'url' => '/v1/products',
        ]);
    }

    private function mockStripePrices($environment)
    {
        return Collection::constructFrom([
            'object' => 'list',
            'data' => [
                StripePrice::constructFrom([
                    'id' => "price_{$environment}1",
                    'object' => 'price',
                    'active' => true,
                    'billing_scheme' => 'per_unit',
                    'created' => time(),
                    'currency' => 'usd',
                    'custom_unit_amount' => null,
                    'livemode' => $environment === 'live',
                    'lookup_key' => 'my-price-lookup-key',
                    'metadata' => [],
                    'nickname' => null,
                    'product' => "prod_{$environment}1",
                    'recurring' => [
                        'aggregate_usage' => null,
                        'interval' => 'month',
                        'interval_count' => 1,
                        'trial_period_days' => null,
                        'usage_type' => 'licensed',
                    ],
                    'tax_behavior' => 'unspecified',
                    'tiers_mode' => null,
                    'transform_quantity' => null,
                    'type' => 'recurring',
                    'unit_amount' => 1000,
                    'unit_amount_decimal' => '1000',
                ]),
                StripePrice::constructFrom([
                    'id' => "price_{$environment}2",
                    'object' => 'price',
                    'active' => true,
                    'billing_scheme' => 'per_unit',
                    'created' => time(),
                    'currency' => 'usd',
                    'custom_unit_amount' => null,
                    'livemode' => $environment === 'live',
                    'lookup_key' => 'my-other-price-key',
                    'metadata' => [],
                    'nickname' => null,
                    'product' => "prod_{$environment}1",
                    'recurring' => [
                        'aggregate_usage' => null,
                        'interval' => 'year',
                        'interval_count' => 1,
                        'trial_period_days' => null,
                        'usage_type' => 'licensed',
                    ],
                    'tax_behavior' => 'unspecified',
                    'tiers_mode' => null,
                    'transform_quantity' => null,
                    'type' => 'recurring',
                    'unit_amount' => 2000,
                    'unit_amount_decimal' => '2000',
                ]),
            ],
            'has_more' => false,
            'url' => '/v1/prices',
        ]);
    }
}
