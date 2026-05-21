<?php

namespace Tests\Feature;

use App\Billing\Charges\StandardCharge;
use App\Http\Controllers\Client\CreateCheckoutService;
use App\Models\Account\Agent;
use App\Models\Account\Customer;
use App\Models\Billing\BillingProvider;
use App\Models\Billing\Charge;
use App\Models\Billing\Schedule;
use App\Models\Billing\Subscription;
use App\Models\Catalog\Feature;
use App\Models\Catalog\Inclusion;
use App\Models\Catalog\Product;
use App\Models\Client;
use App\Models\Convert\CheckoutState;
use App\Models\Convert\Flow;
use App\Models\Intelligence\Scenario;
use App\Models\Management\Organization;
use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use App\Models\Pricing\Scheme;
use App\Models\Stats\Collector;
use App\Models\Store\Purchase;
use App\Models\Twin;
use App\Models\Usage\Metric;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Database\Seeders\AdminSeeder;
use Firebase\JWT\JWT;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\PaymentMethod;
use Stripe\Subscription as StripeSubscription;
use Symfony\Component\Uid\Ulid;
use Tests\Helper\TestsAgainstStripe;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use TestsAgainstStripe;

    protected function create_basic_account()
    {
        $org = Organization::factory()->create();
        $client = Client::factory()->for($org)->create([]);
        $billing = BillingProvider::factory()->for($org)->stripe()->create();
        $client->billingProvider()->associate($billing)->save();
        $scheme = Scheme::factory()->for($org)->create();
        $workflow = Flow::factory()
            ->for($org)
            ->create();
        //        $client->workflows()->attach($workflow);
        $plan = Plan::factory()
            ->for($org)
            ->create();
        $agent = Agent::factory()->for($org)->create();
        $customer = Customer::factory()
            ->for($org)
            ->for($billing, 'billingProvider')
            ->create();
        $plan = Plan::factory()
            ->for($org)
            ->create([
                'currency' => 'usd',
            ]);

        $schedule = Schedule::factory()
            ->for($customer)
            ->for($org)
            ->create();

        Subscription::factory()
            ->for($schedule)
            ->for($org)
            ->for($plan)
            ->create();

        $paywall = Scenario::factory()
            ->for($org)
            ->for($workflow)
            ->for($scheme)
            ->create();

        $twinId = DB::table('twins')
            ->insertGetId([
                'reference_id' => $customer->reference_id,
                'organization_id' => $org->id,
                'connector_id' => $billing->id,
                'type' => 'customer',
                'data' => json_encode([
                    'id' => $customer->reference_id,
                    'object' => 'customer',
                    'created' => $customer->created_at->timestamp,
                    'email' => $customer->email,
                    'name' => $customer->name,
                    'invoice_settings' => [
                        'default_payment_method' => null,
                    ],
                    'discount' => null,
                ]),
                'linkable_id' => $customer->id,
                'linkable_type' => 'customer',
            ]);

        return [
            $org,
            $client,
            $billing,
            $scheme,
            $workflow,
            $paywall,
            $agent,
            $customer,
        ];
    }

    public function test_basic_commit_mutation()
    {
        [
            $org,
            $client,
            $connection,
            $scheme,
            $workflow,
            $paywall,
            $agent,
            $customer,
        ] = $this->create_basic_account();

        $product = Product::factory()
            ->for($org)
            ->create();
        // todo: twin needs to be created?!
        $baseCharge = Charge::factory()
            ->for($org)
            ->for($product)
            ->standard()
            ->create([
                'currency' => 'usd',
            ]);
        $package = Package::factory()
            ->for($scheme)
            ->create();

        $plan = Plan::factory()
            ->for($org)
            ->for($package)
            ->create([
                'currency' => 'usd',
            ]);
        Inclusion::factory()
            ->for($baseCharge, 'charge')
            ->for($plan)
            ->create();

        $feature = Feature::factory()
            ->for($org)
            ->create();
        $inclusion = Inclusion::factory()
            ->for($plan)
            ->for($feature)
            ->create();

        DB::table('pricing_packages')
            ->insert([
                'pricing_scheme_id' => $scheme->id,
                'lookup_key' => $product->lookup_key,
                'name' => 'test',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $checkout = Purchase::factory()
//            ->for($paywall, 'initiator')
            ->for($org)
            ->for($client->billingProvider, 'billing_provider')
//            ->for($agent)
//            ->for($scheme)
            ->for($customer)
//            ->started()
            ->create([
//                'line_items' => [
//                    [
//                        'id' => $plan->getRouteKey(),
//                        'object' => 'plan',
//                    ],
//                ],
//                'config' => [],
                'currency' => 'USD',
            ]);

        $checkout->items()->create([
            'purchasable_id' => $plan->id,
            'purchasable_type' => 'plan',
            'quantity' => 1,
        ]);

        $foundPlan = Plan::query()
            ->where('organization_id', $org->id)
            ->where('lookup_key', $plan->getRouteKey())
            ->first();
        $this->assertNotNull($foundPlan);

        // todo: test models are set up correctly?

        $priceTwinId = DB::table('twins')
            ->insertGetId([
                'organization_id' => $org->id,
                'connector_id' => $connection->id,
                'reference_id' => $foundPlan->lookup_key,
                'type' => 'price',
                'data' => json_encode([
                    'id' => $foundPlan->lookup_key,
                    'object' => 'price',
                ]),
                'linkable_id' => $foundPlan->charges()->first()->id,
                'linkable_type' => 'charge',
            ]);

        $newSubscription = StripeSubscription::constructFrom([
            'id' => 'sub_'.Str::random(20),
            'object' => 'subscription',
            'customer' => $customer->stripe_id,
            'items' => [
                [
                    'id' => 'si_'.Str::random(20),
                    'object' => 'subscription_item',
                    'plan' => [
                        'id' => $foundPlan->lookup_key,
                        'object' => 'plan',
                    ],
                    'price' => [
                        'id' => $foundPlan->lookup_key,
                        'object' => 'price',
                    ],
                ],
            ],
        ]);

        // create a pm for hte customer
        $pm = Twin::factory()
            ->for($org)
            ->for($connection, 'connector')
            ->create([
                'reference_id' => $pmid = 'pm_'.Str::random(20),
                'type' => PaymentMethod::class,
                'data' => [
                    'id' => $pmid,
                    'object' => 'payment_method',
                ],
            ]);
        $customer->payment_methods = [$pm->id];
        $customer->save();

        $subscriptionList = [
            'object' => 'list',
            'url' => '/v1/subscriptions',
            'has_more' => false,
            'data' => [$newSubscription],
        ];

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects($this->atLeastOnce())
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                [json_encode($subscriptionList), 200, []],
                [json_encode([
                    'object' => 'subscription',
                    'id' => 'sub_'.Str::random(32),
                ]), 200, []], //sync get subscriptions
                [json_encode([
                    'object' => 'customer',
                    'data' => [
                        'id' => 'cust_xxx',
                        'name' => 'test',
                        'email' => fake()->email,
                        'invoice_settings' => [
                            'default_payment_method' => $pm->reference_id,
                        ],
                        'currency' => 'usd',
                        'created' => now()->timestamp,
                        'discount' => null,
                    ],
                ]), 200, []], //update
                [json_encode($this->makeStripePaymentMethodList()), 200, []],
                [json_encode($subscriptionList), 200, []],
            );
        ApiRequestor::setHttpClient($httpClient);

        $mutateRes = $this->postJson(
            'client/checkouts/'.$checkout->getRouteKey().'/mutations',
            [
                'action' => 'commit',
            ],
            [
                'Authorization' => 'Bearer '.JWT::encode(['sub' => Str::uuid()->toString()], $client->getSecretStr(), 'HS256', $client->getRouteKey()),
            ]
        );
        $mutateRes->assertOk();
        $data = $mutateRes->json();

        $customer->refresh();

        $this->assertNotNull($customer->schedule);

        $this->assertSame($checkout->getRouteKey(), $data['id']);
        $this->assertSame('checkout', $data['object']);
        $this->assertSame(CheckoutState::COMPLETED->value, $data['current_state']);
        $this->assertCount(1, $data['line_items']);
        $this->assertNotNull($data['customer']);

    }

    public function test_checkout_with_metric_quantity()
    {
        [
            $org,
            $client,
            $connection,
            $offering,
            $workflow,
            $paywall,
            $agent,
            $customer,
        ] = $this->create_basic_account();

        $feature = Feature::factory()
            ->for($org)
            ->create();
        $metric = Metric::factory()
            ->for($org)
            ->for($feature)
            ->create([
                'event_name' => 'team_members',
                'field_name' => 'team_members_count',
            ]);
        $product = Product::factory()
            ->for($org)
            ->create([
                'name' => 'Team Plan',
            ]);
        // insert into productfeature
        DB::table('catalog_product_features')
            ->insert([
                'product_id' => $product->id,
                'feature_id' => $feature->id,
            ]);

        $baseCharge = StandardCharge::factory()
            ->for($org)
            ->for($product)
            ->standard()
            ->create([
                'currency' => 'USD',
            ]);
        $package = Package::factory()
            ->for($offering)
            ->create();

        $plan = Plan::factory()
            ->for($org)
            ->for($package)
            ->create([
                'name' => 'Team Plan',
                'currency' => 'USD',
            ]);
        Inclusion::factory()
            ->for($plan)
            ->for($product)
            ->for($baseCharge, 'charge')
            ->for($metric)
            ->create();

        $paywall->save();

        DB::table('usage_summaries')
            ->insert([
                'organization_id' => $org->id,
                'metric_id' => $metric->id,
                'customer_id' => $customer->id,
                'current_aggregation' => 5,
                'event_name' => $metric->event_name,
                'latest_event_id' => 1,
            ]);

        $agent->associateWithCustomer($customer);

        $collector = Collector::factory()
            ->for($client)
            ->create();

        $bsp = BillingProvider::factory()
            ->for($org)
            ->stripe()
            ->create();

        // change this to purchase/checkout
        $purchase = Purchase::factory()
            ->for($org)
            ->for($bsp, 'billing_provider')
            ->create([
                'currency' => $plan->currency,
            ]);
        $item = $purchase->items()->create([
            'purchasable_id' => $plan->id,
            'purchasable_type' => 'plan',
            'quantity' => CreateCheckoutService::getItemQuantity($plan, $customer),
        ]);

        $createRes = $this->getJson('/client/checkouts/'.$purchase->getRouteKey(), [
            'Plandalf-Cart' => $purchase->getRouteKey().'?sig='.hash_hmac('sha256', $purchase->getRouteKey(), config('app.key')),
            'Authorization' => 'Bearer '.JWT::encode([
                'sub' => $agent->getRouteKey(),
                'customer' => $customer->reference_id,
            ], $client->getSecretStr(), 'HS256', $client->getRouteKey()),
        ]);
        $createRes->assertOk();

        $li = $createRes->json('line_items.0');
        $this->assertNotNull($li);

        $this->assertSame($plan->getRouteKey(), $li['id']);
        $this->assertSame('plan', $li['object']);
        $this->assertSame(5, $li['quantity']['value']);
        $this->assertSame($baseCharge->amount->multiply(5)->getAmount(), Arr::get($li, 'amount_subtotal'));
    }

    /**
     * Test a new user subscribing to a plan to get rollups while on a free plan
     */
    public function test_agent_can_create_checkout(): void
    {
        $this->markTestIncomplete();
        $org = Organization::factory()
            ->create();
        /* @var Client $client */
        $client = Client::factory()
            ->for($org)
            ->web()
            ->create();

        $offering = $client->offerings()->first();

        $userId = Str::uuid()->toString();
        $groupId = Str::random(6).'-'.Str::slug(fake()->company);

        $encodedJwt = JWT::encode([
            'sub' => $userId,
            'groups' => [$groupId],
        ], $client->getSecretStr(), 'HS256', $client->getRouteKey());

        // todo: this user needs to "exist" already in some billing system
        // todo: hook up the connection

        $uri = new Uri(fake()->url);

        $res = $this->post('/api/v1/clients/'.$client->getRouteKey().'/session', [
            'token' => $encodedJwt,
            'tz' => '',
        ], [
            'Origin' => $origin = $uri->withPath(''),
            'Referer' => $uri,
            'Accept-Language' => 'en-US,en;q=0.9',
            'User-Agent' => $ua = fake()->userAgent,
        ]);
        $res->assertCreated();

        $agent = Agent::query()
            ->where('lookup_key', $userId)
            ->first();
        $this->assertNotNull($agent);
        $this->assertSame($org->id, $agent->organization_id);
        $this->assertNull($agent->customer_id);

        // todo: see user is not subscribed to any plans
        // todo: add stripe connection

        $clientSession = ClientSession::retrieve($res->json('id'));
        $this->assertSame($client->id, $clientSession->client_id);
        $this->assertSame('127.0.0.1', $clientSession->ip_address);
        $this->assertSame($agent->id, $clientSession->agent_id);
        $this->assertSame($offering->id, $clientSession->offering_id);
        $this->assertSame(strval($origin), $clientSession->origin);

        $resAgent = Agent::query()
            ->where('organization_id', $org->id)
            ->where('lookup_key', $res->json('agent.id'))
            ->first();
        $this->assertSame($agent->id, $resAgent->id);
        // todo :verification involves checking JWT
        $this->assertFalse($res->json('agent.is_verified'));
        $this->assertNull($res->json('customer'));
        $this->assertNull($res->json('schedule'));

        $resOffering = $res->json('config.catalog.offering');
        $workflows = $res->json('modules.workflows');

        $workflow = Flow::factory()
            ->for($org)
            ->create([
                'name' => 'trad workflow',
                'triggers' => [
                    [
                        // listen for an element to attach to the dom
                        //                        'listen' => 'attachment', // event, manual
                        'listen' => 'event', // event, manual
                        'event' => 'RollupButtonClicked',
                        'qualifier' => 'findButtonByText',
                        'props' => [
                            'text' => 'Create Business',
                        ],
                    ],
                ],

                // which features are we blocking
            ]);

        ClientWorkflow::factory()
            ->for($workflow)
            ->for($client)
            ->create();

        // compare trad approach to new approach
        // trad = 1. see some info in a modal 2. see redirect 3. see upgrade
        // new  = 1. open paywall 2. start checkout 3. complete checkout

        // todo: variants?
        // ?: do we need events on paywall to tell if our baseline works properly>

        // variant controls the rules ?
        // paywall variant
        // customer
        // look them up and see there is none
        // run filters

        // Variants:
        // 1 seats - Already on paid starter or pro plan - Add growth lite add on $30
        // 1 seats - Not paying yet (trial or free) - buy pro plan AND add on (combined $59)
        // 2 seats or more, just show growth plan $99

        $paywall = Scenario::factory()
            ->for($workflow)
            ->for($org)
            ->bonjoroFreeToPaid()
            ->create([]);

        $res = $this->post('/api/v1/clients/'.$client->getRouteKey().'/session', [
            'token' => $encodedJwt,
            'session_id' => $clientSession->getRouteKey(),
            'tz' => '',
        ], [
            'Origin' => $origin = $uri->withPath(''),
            'Referer' => $uri,
            'Accept-Language' => 'en-US,en;q=0.9',
            'User-Agent' => $ua = fake()->userAgent,
        ]);
        $paywallToShowId = $res->json('modules.workflows.0.paywalls.default.0.id');

        $pw = Scenario::retrieve($paywallToShowId);
        $this->assertNotNull($pw);

        $params = Query::build([
            'session_id' => $clientSession->getRouteKey(),
            'token' => $encodedJwt,
        ]);

        $eventRes = $this->postJson('/api/v1/clients/'.$client->getRouteKey().'/events', [
            'session_id' => $clientSession->getRouteKey(),
            'token' => $encodedJwt,
            'events' => [
                [
                    'event' => '$pd/paywall.entry',
                    'properties' => [
                        'paywall_id' => $paywallToShowId,
                        'trigger_id' => 'button-click',
                    ],
                ],
            ],
        ]);

        // todo: match the current requester to the paywall

        $pwReq = $this->getJson('/api/v1/clients/'.$client->getRouteKey().'/paywalls/'.$paywallToShowId.'?'.$params);

        // todo: validate the pqreq here to make sure it matches the paywall

        $checkoutSessionResponse = $this->postJson('/api/v1/clients/checkout/sessions', [

            //client_reference_id

            'session_id' => $clientSession->getRouteKey(),
            'token' => $encodedJwt,
            'paywall_id' => $paywallToShowId,
            // options?

            // customer-> pass customer or customer_Id
            // todo: need to return payment info and checkout type
            //

            'line_items' => [
                [
                    'type' => 'plan',
                    'price' => 'price_xxxxxx',
                    // charge
                    //                    'plan' => 'pro-annual-1',
                    'quantity' => 1,
                ],
                [
                    'type' => 'addon',
                    'plan' => 'growth-lite',
                    'quantity' => 1,
                    // quantity editable
                    // removable?
                ],
                // give "alternative" configurations for line item 1
            ],
            // values from paywall get included
            // overrides can update the checkout
        ]);

        // update line items values from the ui

        // todo: return info about the price / plan that user is selecting
        dump($checkoutSessionResponse->status(), $checkoutSessionResponse->json());
        // post to checkout events to update
        //
    }

    public function test_checkout_lifecycle()
    {
        // operation: create, update, commit, complete, navigate, cancel, fail
        $this->seed(AdminSeeder::class);

        $org = Organization::query()
            ->where('name', 'flindev')
            ->first();
        $client = $org->clients()->first();
        $billing = $org->liveBillingProvider;
        $this->markTestIncomplete();
        /* @var Scheme $offering */
        $offering = $org->schemes()->first();
        $this->assertNotNull($offering);

        $workflow = Flow::factory()
            ->for($org)
            ->create([
                'name' => 'trad workflow',
                'triggers' => [
                    [
                        'id' => $triggerId = Str::uuid()->toString(),
                        'listen' => 'event',
                        'event' => 'RollupButtonClicked',
                        'qualifier' => 'findButtonByText',
                        'props' => [
                            'text' => 'Create Business',
                        ],
                    ],
                ],
            ]);

        $agent = Agent::factory()
            ->for($org)
            ->create([
                'lookup_key' => $userId = Str::uuid()->toString(),
            ]);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects($this->atLeastOnce())
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                [json_encode($this->setupIntent()), 200, []],
            );
        ApiRequestor::setHttpClient($httpClient);

        //        dd($offering->plans->toArray());
        $addonPlan = $offering
            ->plans
            ->firstWhere('name', 'usd-roll-up-limit-add-on-yearly-360');
        $proPlan = $offering
            ->plans
            ->firstWhere('name', 'vm-pro-yearly-2023');

        $paywall = Scenario::factory()
            ->for($offering)
            ->for($workflow)
            ->for($billing)
            ->for($org)
            ->bonjoroFreeToPaid()
            ->create([
                'ulid' => Ulid::fromBase58('1C9KdmB822DirjPnnvo7Lx'),
                'checkout_config' => [
                    'line_items' => [
                        [
                            'object' => 'plan',
                            'id' => $proPlan->getRouteKey(),
                            'quantity' => 1,
                        ],
                        [
                            'object' => 'plan',
                            'id' => $addonPlan->getRouteKey(),
                            'quantity' => 1,
                        ],
                    ],
                ],
            ]);

        // create, start, update, commit, cancel, fail, complete
        $checkoutSessionResponse = $this->postJson('/api/v1/clients/'.$client->getRouteKey().'/checkouts', [
            'paywall' => $paywall->getRouteKey(),
            'trigger' => $triggerId, // todo: encode better
        ], [
            'Authorization' => 'Bearer '.JWT::encode([
                'sub' => $userId,
            ], $client->getSecretStr(), 'HS256', $client->getRouteKey()),
        ]);

        //        dd($checkoutSessionResponse->exception, $checkoutSessionResponse->json());

        $checkout = Checkout::query()
            ->where('id', $checkoutSessionResponse->json('id'))
            ->first();
        $this->assertNotNull($checkout);

        $this->assertSame(CheckoutState::CREATED, $checkout->current_state);

        //        dd($checkout->getRouteKey(),$checkout->toArray());

        $authz = 'Bearer '.JWT::encode(['sub' => $userId], $client->getSecretStr(), 'HS256', $client->getRouteKey());

        $updateRes = $this->patch('/api/v1/clients/'.$client->getRouteKey().'/checkouts/'.$checkout->getRouteKey(), [
            'current_state' => 'started',
        ], [
            'Authorization' => $authz,
        ]);
        // create mutation to update the checkout

        $updateRes->assertOk();

        // get analytics for checkouts in a "period"
        //        DB::table('convert_checkouts')
        //            ->where('created_at', '>', Carbon::now()->subDays(7))
        //            ->get();
        $results = $this->getCheckoutResults($org);
        $yearWeek = Carbon::now()->format('Y-W');
        $item = $results->firstWhere('year_week', $yearWeek);
        $this->assertNotNull($yearWeek);
        $this->assertSame(1, $item->entered);
        $this->assertSame(0, $item->started_checkout);
        $this->assertSame(0, $item->completed_checkout);
        $this->assertSame(0.0, $item->started_checkout_percentage);
        $this->assertSame(0.0, $item->completed_checkout_percentage);

        $checkout->refresh();
        $this->assertSame(CheckoutState::STARTED, $checkout->current_state);

        // get analytics for checkouts in a "period"
        $mutationRes = $this->post('/api/v1/clients/'.$client->getRouteKey().'/checkouts/'.$checkout->getRouteKey().'/mutations', [
            'action' => 'start',
            'props' => [],
        ], [
            'Authorization' => $authz,
        ]);

        $results = $this->getCheckoutResults($org);
        $item = $results->firstWhere('year_week', $yearWeek);
        $this->assertNotNull($yearWeek);
        $this->assertSame(1, $item->entered);
        $this->assertSame(1, $item->started_checkout);
        $this->assertSame(0, $item->completed_checkout);
        $this->assertSame(100.0, $item->started_checkout_percentage);
        $this->assertSame(0.0, $item->completed_checkout_percentage);

        // finalise
        $mutationRes = $this->post('/api/v1/clients/'.$client->getRouteKey().'/checkouts/'.$checkout->getRouteKey().'/mutations', [
            'action' => 'commit',
            'props' => [],
        ], [
            'Authorization' => $authz,
        ]);

        $results = $this->getCheckoutResults($org);
        $item = $results->firstWhere('year_week', $yearWeek);
        $this->assertNotNull($yearWeek);
        $this->assertSame(1, $item->entered);
        $this->assertSame(1, $item->started_checkout);
        $this->assertSame(1, $item->completed_checkout);
        $this->assertSame(100.0, $item->started_checkout_percentage);
        $this->assertSame(100.0, $item->completed_checkout_percentage);

        // associate and create customer

        // setup intent will associate a customer and create a payment method
        // pull these from the stripe api
        // update the customer and payment method
    }

    public function test_correct_subscription_interval()
    {
        [$org] = $this->create_basic_account();

        $customer = Customer::factory()
            ->for($org)
            ->create();
        $this->assertSame(null, $customer->getCurrentBillingInterval());

        $plan1 = Plan::factory()
            ->for($org)
            ->create([
                'currency' => 'usd',
                'renew_interval' => 'P1Y',
            ]);
        $plan2 = Plan::factory()
            ->for($org)
            ->create([
                'currency' => 'usd',
                'renew_interval' => 'P1M',
            ]);

        $sub1 = Subscription::factory()
            ->for($plan1)
            ->for($customer)
            ->for($org)
            ->create([
                'current_state' => 'inactive',
            ]);

        $sub2 = Subscription::factory()
            ->for($plan2)
            ->for($customer)
            ->for($org)
            ->create([
                'current_state' => 'active',
            ]);

        $customer->refresh();
        $this->assertTrue(
            CarbonInterval::make('P1M')->eq($customer->getCurrentBillingInterval())
        );
    }

    private function getCheckoutResults(Organization $organization)
    {
        return DB::table('convert_checkouts')
            ->select(
                DB::raw("strftime('%Y', entered_at) || '-' || strftime('%W', entered_at) AS year_week"),
                DB::raw('COUNT(*) as entered'),
                DB::raw('SUM(CASE WHEN has_started = 1 THEN 1 ELSE 0 END) as started_checkout'),
                DB::raw('SUM(CASE WHEN has_completed = 1 THEN 1 ELSE 0 END) as completed_checkout')
            )
            //            ->where('organization_id', $organization->id)
            ->where('has_entered', true)
            ->groupBy('year_week')
            ->get()
            ->map(function ($row) {
                $row->started_checkout_percentage = $row->entered ? round(($row->started_checkout / $row->entered) * 100, 2) : 0.0;
                $row->completed_checkout_percentage = $row->started_checkout ? round(($row->completed_checkout / $row->started_checkout) * 100, 2) : 0.0;

                return $row;
            });
    }

    private function setupIntent(): array
    {
        return [
            'id' => 'seti_1Mm8s8LkdIwHu7ix0OXBfTRG',
            'object' => 'setup_intent',
            'client_secret' => 'seti_1Mm8s8LkdIwHu7ix0OXBfTRG_secret_NXDICkPqPeiBTAFqWmkbff09lRmSVXe',
            'created' => 1678942624,
            'livemode' => false,
            'payment_method_types' => [
                'card',
            ],
            'status' => 'requires_payment_method',
            'usage' => 'off_session',
            //            'use_stripe_sdk' => true,

        ];
    }
}
