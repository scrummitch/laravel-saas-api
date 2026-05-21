<?php

namespace Tests\Unit;

use App\Jobs\ConfigureInitialPricingSchemeJob;
use App\Jobs\Services\Stripe\ImportStripeCustomersJob;
use App\Jobs\Services\Stripe\ImportStripePricesJob;
use App\Jobs\Services\Stripe\ImportStripeProductsJob;
use App\Jobs\Services\Stripe\ImportStripeSubscriptionsJob;
use App\Models\Account\Customer;
use App\Models\Catalog\Inclusion;
use App\Models\Catalog\Product;
use App\Models\Pricing\Plan;
use App\Models\Pricing\Scheme;
use App\Models\Twin;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class ImportStripeAccountTest extends TestCase
{
    protected $seed = true;

    /**
     * A basic unit test example.
     */
    public function test_import_stripe_account(): void
    {
        $this->markTestIncomplete();
        $org = $this->createOrg();

        /**
         * Test import customers
         */
        $customerIds = [
            'cus_'.Str::random(16),
            'cus_'.Str::random(16),
        ];

        $customersResponse = $this->stripeCustomersListResponse($customerIds);
        $this->mockStripe([[json_encode($customersResponse), 200, []]]);
        ImportStripeCustomersJob::dispatchSync($stripeConnection);

        foreach ($customersResponse['data'] as $customerData) {
            $this->assertDatabaseHas('account_customers', [
                'organization_id' => $org->id,
                'reference_id' => $customerData['id'],
                'email' => $customerData['email'],
                'name' => $customerData['name'],
            ]);
            $customer = Customer::where('reference_id', $customerData['id'])->first();
            $this->assertNotNull($customer);
            $twin = $customer->twins()->first();
            $this->assertNotNull($twin);
        }

        /**
         * Test import products
         */
        $productsResponse = $this->stripeProductsListResponse();
        $this->mockStripe([[json_encode($productsResponse), 200, []]]);
        ImportStripeProductsJob::dispatchSync($stripeConnection);

        foreach ($productsResponse['data'] as $productData) {
            $this->assertDatabaseHas('catalog_products', [
                'organization_id' => $org->id,
                'key' => $productData['id'],
            ]);
        }

        // featureset - msesage templates
        // feature - message template limit
        // feature - messsage template advanced function
        // feature sets dont really apply to a product?

        // TODO: there should be ONE package with TWO plans

        // TODO: add a feature to pro plan
        $featureSet = $org->featureSets()->create([
            'name' => 'Pro Feature Set',
            'key' => 'pro-feature-set',
            'description' => 'Pro Feature Set description',
        ]);
        $feature = $org->features()->create([
            'feature_set_id' => $featureSet->id,
            'name' => 'Pro Feature',
            'key' => 'pro-feature',
            'description' => 'Pro Feature description',
        ]);
        /* @var Product $proProduct */
        $proProduct = $org
            ->products()
            ->where('name', 'Pro Product')->first();

        $inclusion = new Inclusion;
        $inclusion->target_id = $proProduct->id;
        $inclusion->target_type = 'product';
        $inclusion->feature_id = $feature->getKey();
        $inclusion->display_name = 'Special pro inclusion!';
        $inclusion->description = 'for special boys and girls';
        $inclusion->save();

        /**
         * Test import prices
         */
        $productTwins = Twin::query()
            ->whereIn('reference_id', collect($productsResponse['data'])->pluck('id'))
            ->get();
        foreach ($productTwins as $productTwin) {
            $this->assertNotNull($productTwin->reference_id);
            $this->assertNotNull($productTwin->data['id']);
        }

        $pricesResponse = $this->stripePricesListResponse($productTwins);
        $this->mockStripe([
            [json_encode($pricesResponse), 200, []],
        ]);
        ImportStripePricesJob::dispatchSync($stripeConnection);

        $customers = Customer::query()
            ->where('organization_id', $org->id)
            ->get();
        $plans = Plan::query()
            ->where('organization_id', $org->id)
            ->get();

        $subscriptions = $this->stripeSubscriptionsListResponse($customers, $plans);
        $this->mockStripe([
            [json_encode($subscriptions), 200, []],
        ]);
        ImportStripeSubscriptionsJob::dispatch($stripeConnection);

        // group plans by their "interval", or are they just prices on product?

        $this->assertCount(2, $org->customers);
        $this->assertCount(3, $org->plans);
        $this->assertCount(2, $org->products);

        ConfigureInitialPricingSchemeJob::dispatch($stripeConnection);

        /* @var Scheme $offering */
        $scheme = $org->schemes()->first();

        $this->assertCount(3, $scheme->plans);
        $this->assertCount(2, $scheme->plans->pluck('pivot.name')->unique());

        $c = $org
            ->customers()
            ->with(['schedule', 'schedule.plans', 'schedule.plans.baseCharge'])
            ->get();

        $products = $org->products;

        $proProduct = $products
            ->where('name', 'Pro Product')
            ->first();

        //        $customer = $c->get(1);
        //        dd(
        //            $customer->entitlements,
        //        );
        //        dd($proProduct);

        // TODO: see pro plan sub has it and less-pro doesnt  !
        // TODO: see charge amounts are correct
        // TODO: create a free plan and add a user to that.
        // todo: test inactive plans?
        // todo: payment methods on customers
        // todo: determine the differnce between plan_inclusion and product_feature?

        // feature_set: groups of features that are shipped together?
        // feature: a single feature that can be added to a product

        // procustomer is the customer subscribed to the plan which gives
        // acces to the pro product
        $proCustomer = $c->get(1);

        // get the base charge?
        $charge = $proCustomer
            ->schedule
            ->plans
            ->first()
            ->baseCharge;

        // why is it always via the charge?
        // plan->charges based on entitlements?
        // products have implicit features???
        //

        $charge->loadMissing(['product', 'product.inclusions']);

        $proProduct->loadMissing('inclusions');
        $proCustomer->refresh();

        // note: should an inclusion always be related a product?
        //

        // products have features
        // paying for them gets access to those features? or not?
        // relating products to plans gives access to them via the plan?
        //
        // catalog_inclusions: [feature_id] -> inclusion->reference, containment
        // subset, superset,

        // catalog_inclusions

    }

    protected function stripeSubscriptionsListResponse(Collection $customers, Collection $plans)
    {
        return [
            'object' => 'list',
            'has_more' => false,
            'data' => $customers
                ->map(function (Customer $customer, int $index) use ($plans) {
                    $plan = $plans->get($index);

                    return [
                        'id' => 'sub_'.Str::random(12),
                        'object' => 'subscription',
                        'customer' => $customer->reference_id,
                        'items' => [
                            [
                                'id' => 'si_'.Str::random(12),
                                'object' => 'subscription_item',
                                'price' => $plan->name,
                                'quantity' => 1,
                            ],
                        ],
                        'created' => time(),

                    ];
                })
                ->toArray(),
        ];
    }

    protected function stripePricesListResponse(Collection $products)
    {
        return [
            'object' => 'list',
            'has_more' => false,
            'data' => [
                [
                    'id' => 'price_'.Str::random(12),
                    'object' => 'price',
                    'active' => true,
                    'billing_scheme' => 'per_unit',
                    'currency' => 'usd',
                    'unit_amount' => 2000,
                    'product' => $products->get(0)->reference_id,
                    'type' => 'recurring',
                    'recurring' => [
                        'interval' => 'month',
                        'interval_count' => 1,
                    ],
                    'metadata' => [
                        'description' => 'Price 1.0 description',
                    ],
                    'created' => time(),
                ],
                [
                    'id' => 'price_'.Str::random(12),
                    'object' => 'price',
                    'active' => true,
                    'billing_scheme' => 'per_unit',
                    'currency' => 'usd',
                    'unit_amount' => 20000,
                    'product' => $products->get(0)->reference_id,
                    'type' => 'recurring',
                    'recurring' => [
                        'interval' => 'year',
                        'interval_count' => 1,
                    ],
                    'metadata' => [
                        'description' => 'Price 1.1 description',
                    ],
                    'created' => time(),
                ],
                [
                    'id' => 'price_'.Str::random(12),
                    'object' => 'price',
                    'active' => true,
                    'billing_scheme' => 'per_unit',
                    'currency' => 'usd',
                    'unit_amount' => 1000,
                    'product' => $products->get(1)->reference_id,
                    'type' => 'recurring',
                    'recurring' => [
                        'interval' => 'month',
                        'interval_count' => 1,
                    ],
                    'metadata' => [
                        'description' => 'Price 2.0 description',
                    ],
                    'created' => time(),
                ],
            ],
        ];
    }

    protected function stripeProductsListResponse()
    {
        return [
            'object' => 'list',
            'has_more' => false,
            'data' => [
                [
                    'id' => 'prod_'.Str::random(12),
                    'object' => 'product',
                    'name' => 'Pro Product',
                    'type' => 'service',
                    'active' => true,
                    'metadata' => [
                        'description' => 'Product 1 description',
                    ],
                    'unit_label' => 'unit',
                    'description' => 'Product 1 description',
                    'statement_descriptor' => 'Product 1',
                    'created' => Carbon::now()->subMonth()->timestamp,
                ],
                [
                    'id' => 'prod_'.Str::random(12),
                    'object' => 'product',
                    'name' => 'Less Pro Product',
                    'type' => 'service',
                    'active' => true,
                    'metadata' => [
                        'description' => 'Product 2 description',
                    ],
                    'unit_label' => 'unit',
                    'description' => 'Product 2 description',
                    'statement_descriptor' => 'Product 2',
                    'created' => Carbon::now()->subMonth()->subDay()->timestamp,
                ],
            ],
        ];
    }

    protected function stripeCustomersListResponse(array $identifiers): array
    {
        return [
            'object' => 'list',
            'has_more' => false,
            'data' => Collection::make($identifiers)
                ->map(function ($id) {
                    return [
                        'id' => $id,
                        'object' => 'customer',
                        'name' => 'John Doe',
                        'email' => fake()->email,
                        'currency' => 'usd',
                        'timezone' => 'America/New_York',
                        'created' => time(),
                    ];
                })
                ->toArray(),
        ];
    }

    protected function mockStripe(array $responses)
    {
        $httpClient = $this->mock(ClientInterface::class);
        $index = 0;

        $httpClient
            ->expects('request')
            ->times(count($responses))
            ->andReturnUsing(function ($method, $url, $options) use ($responses, &$index) {
                logger()->info(logname('intercept'), [
                    'method' => $method,
                    'url' => $url,
                    'options' => $options,
                ]);

                return $responses[$index++];
            });

        ApiRequestor::setHttpClient($httpClient);
    }
}
