<?php

namespace Tests\Feature\API;

use App\Models\Billing\BillingProvider;
use App\Models\Billing\Charge;
use App\Models\Catalog\Product;
use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use App\Models\Pricing\Scheme;
use App\Models\Twin;
use Illuminate\Support\Str;
use Stripe\Price;
use Tests\TestCase;

class PricingSchemeApiControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->createUser([]);
        $this->org = $this->user->currentOrganization;
    }

    public function test_can_create_pricing_schemes()
    {
        $this->actingAs($this->user);

        $product = Product::factory()
            ->for($this->org)
            ->create();
        $baseCharge = Charge::factory()
            ->for($this->org)
            ->for($product)
            ->create();
        $bsp = BillingProvider::factory()
            ->stripe()
            ->for($this->org)
            ->create();
        Twin::factory()
            ->for($this->org)
            ->for($bsp, 'connector')
            ->for($baseCharge, 'linkable')
            ->create([
                'reference_id' => $baseCharge->getRouteKey(),
                'type' => Price::class,
                'data' => [
                    'amount' => 1000,
                    'currency' => 'USD',
                    'active' => true,
                    'recurring' => [
                        'interval' => 'month',
                        'interval_count' => 1,
                    ],
                ],
            ]);

        $createResponse = $this->postJson(route('api/pricing.schemes.store'), [
            'lookup_key' => 'test',
            'name' => 'Test Scheme',
            'packages' => [
            ],
        ]);
        $createResponse->assertStatus(201);
        $scheme = Scheme::retrieve($createResponse->json('id'));
        $this->assertNotNull($scheme);

        $this->assertSame('test', $scheme->lookup_key);
        $this->assertSame('Test Scheme', $scheme->name);
        $this->assertSame('v1', $scheme->version_name);
        $this->assertSame(1, $scheme->version_number);

        $package = Package::factory()
            ->for($scheme)
            ->for($this->org)
            ->create();
        $plan = Plan::factory()
            ->for($this->org)
            ->for($package)
            ->create([
                'currency' => 'USD',
                'renew_interval' => 'P1M',
            ]);

        // create a new version
        $newVersionResponse = $this->postJson(route('api/pricing.schemes.store'), [
            'lookup_key' => 'test',
            'name' => 'Test Scheme 2',
            'packages' => [
                $package->getRouteKey(),
            ],
        ]);

        $newVersionResponse->assertStatus(201);
        $schemeV2 = Scheme::retrieve($newVersionResponse->json('id').'@v2');
        $this->assertNotNull($schemeV2);

        $this->assertSame('test', $schemeV2->lookup_key);
        $this->assertSame('Test Scheme 2', $schemeV2->name);
        $this->assertSame('v2', $schemeV2->version_name);
        $this->assertSame(2, $schemeV2->version_number);

        $key = $schemeV2->getRouteKey();
        $this->assertSame('test@v2', $key);

        $getSchemeRes = $this->getJson(route('api/pricing.schemes.show', ['scheme' => $key]));
        $getSchemeRes->assertOk();

        $this->assertSame('test@v2', $getSchemeRes->json('id'));
        $this->assertCount(1, $getSchemeRes->json('plans.data'));
        $this->assertCount(1, $getSchemeRes->json('packages.data'));
        $this->assertCount(1, $getSchemeRes->json('intervals'));
        $this->assertCount(1, $getSchemeRes->json('currencies'));

//        $addPlanRes = $this->postJson(route('api/pricing.schemes.plans.store', ['scheme' => $schemeV2->getRouteKey()]), [
//            'plan' => $plan->getRouteKey(),
//        ]);
//        // doesnt really exist anymore
//        $addPlanRes->assertOk();
//        $schemeV2->refresh();

//        $this->assertCount(1, $schemeV2->packages);
//        $this->assertCount(1, $schemeV2->plans);
//        $this->assertCount(1, $addPlanRes->json('intervals'));

//        $this->assertSame($plan->renew_interval->spec(), $addPlanRes->json('intervals.0.value'));
//        $this->assertCount(1, $addPlanRes->json('currencies'));
//        $this->assertSame('USD', $addPlanRes->json('currencies.0.currency_code'));

        $removePlanRes = $this->deleteJson(route(
            'api/pricing.schemes.plans.destroy', [
                'scheme' => $schemeV2->getRouteKey(),
                'plan' => $plan->getRouteKey(),
            ])
        );
        //        $removePlanRes->assertStatus(204);
        //        $this->assertCount(0, $schemeV2->refresh()->plans);

        //        $getSchemeRes = $this->getJson(route('api/pricing.schemes.show', ['scheme' => $schemeV2->getRouteKey()]));
    }
}
