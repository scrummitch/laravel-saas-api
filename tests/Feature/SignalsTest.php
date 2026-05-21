<?php

namespace Tests\Feature;

use App\Models\Account\Customer;
use App\Models\Account\Signal;
use App\Models\Account\SignalID;
use App\Models\Billing\BillingProvider;
use App\Models\Billing\Charge;
use App\Models\Catalog\Inclusion;
use App\Models\Catalog\Product;
use App\Models\Convert\Flow;
use App\Models\Intelligence\Activity;
use App\Models\Intelligence\Scenario;
use App\Models\Management\Organization;
use App\Models\Pricing\Plan;
use App\Models\Twin;
use App\Models\User;
use App\Models\Values\PlanType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Helper\TestsAgainstStripe;
use Tests\TestCase;

class SignalsTest extends TestCase
{
    use TestsAgainstStripe;

    protected Customer $customer;
    protected Organization $org;
    protected BillingProvider $billing;
    protected Plan $plan;
    protected Plan $higherPlan;
    protected Plan $lowerPlan;
    protected Plan $addonPlan;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $user = $this->createUser();
        $org = $user->currentOrganization;
        $this->org = $org;
        $this->billing = BillingProvider::factory()
            ->for($org)
            ->stripe()
            ->create(['environment' => 'test']);

        $this->customer = Customer::factory()
            ->for($org)
            ->for($this->billing)
            ->create();

        // Create base plan with standard charge
        $baseProduct = Product::factory()->for($org)->create();
        $baseCharge = Charge::factory()
            ->for($org)
            ->for($baseProduct)
            ->standard()
            ->create([
                'type' => 'standard',
                'mode' => 'in_advance',
                'amount' => 1000,
                'currency' => 'USD',
            ]);
        $this->plan = Plan::factory()->for($org)->standard()->create();
        Inclusion::factory()
            ->for($baseCharge, 'charge')
            ->for($this->plan)
            ->create();

        // Create higher tier plan
        $higherProduct = Product::factory()->for($org)->create();
        $higherCharge = Charge::factory()
            ->for($org)
            ->for($higherProduct)
            ->create([
                'type' => 'standard',
                'mode' => 'in_advance',
                'amount' => 2000,
                'currency' => 'USD',
            ]);
        $this->higherPlan = Plan::factory()->for($org)->standard()->create();
        Inclusion::factory()
            ->for($higherCharge, 'charge')
            ->for($this->higherPlan)
            ->create();

        // Create lower tier plan
        $lowerProduct = Product::factory()->for($org)->create();
        $lowerCharge = Charge::factory()
            ->for($org)
            ->for($lowerProduct)
            ->create([
                'type' => 'standard',
                'mode' => 'in_advance',
                'amount' => 500,
                'currency' => 'USD',
            ]);
        $this->lowerPlan = Plan::factory()->for($org)->standard()->create();
        Inclusion::factory()
            ->for($lowerCharge, 'charge')
            ->for($this->lowerPlan)
            ->create();

        // Create addon plan
        $addonProduct = Product::factory()->for($org)->create();
        $addonCharge = Charge::factory()
            ->for($org)
            ->for($addonProduct)
            ->create([
                'type' => 'standard',
                'mode' => 'in_advance',
                'amount' => 500,
                'currency' => 'USD',
            ]);
        $this->addonPlan = Plan::factory()
            ->for($org)
            ->create(['type' => PlanType::addon]);
        Inclusion::factory()
            ->for($addonCharge, 'charge')
            ->for($this->addonPlan)
            ->create();

        // After creating charges, create their corresponding Stripe price twins
        // For base plan charge
        Twin::factory()
            ->for($this->billing, 'connector')
            ->for($this->org)
            ->create([
                'reference_id' => 'price_' . $this->faker->md5,
                'type' => \Stripe\Price::class,
                'data' => [
                    'unit_amount' => 1000,
                    'currency' => 'usd',
                ],
            ])->linkable()->associate($baseCharge)->save();

        // For higher plan charge
        Twin::factory()
            ->for($this->billing, 'connector')
            ->for($this->org)
            ->create([
                'reference_id' => 'price_' . $this->faker->md5,
                'type' => \Stripe\Price::class,
                'data' => [
                    'unit_amount' => 2000,
                    'currency' => 'usd',
                ],
            ])->linkable()->associate($higherCharge)->save();

        // For lower plan charge
        Twin::factory()
            ->for($this->billing, 'connector')
            ->for($this->org)
            ->create([
                'reference_id' => 'price_' . $this->faker->md5,
                'type' => \Stripe\Price::class,
                'data' => [
                    'unit_amount' => 500,
                    'currency' => 'usd',
                ],
            ])->linkable()->associate($lowerCharge)->save();

        // For addon plan charge
        Twin::factory()
            ->for($this->billing, 'connector')
            ->for($this->org)
            ->create([
                'reference_id' => 'price_' . $this->faker->md5,
                'type' => \Stripe\Price::class,
                'data' => [
                    'unit_amount' => 500,
                    'currency' => 'usd',
                ],
            ])->linkable()->associate($addonCharge)->save();
    }

    public function test_trial_conversion_signal()
    {
        $subscriptionId = 'sub_' . $this->faker->md5;
        $charge = $this->plan->inclusions->first()->charge;
        $priceTwin = $charge->twins->first();

        // Mock the Stripe customer response
        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
            [json_encode( [
                'object' => 'list',
                'url' => '/v1/subscriptions',
                'has_more' => false,
                'data' => [
                ]
            ]), 200, []],
        ]);

        $data = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'customer.subscription.updated',
            'livemode' => $this->billing->environment === 'live',
            'account' => $this->billing->lookup_key,
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'customer' => $this->customer->reference_id,
                    'status' => 'active',
                    'items' => [
                        'object' => 'list',
                        'data' => [[
                            'price' => [
                                'id' => $priceTwin->reference_id,
                                'unit_amount' => $charge->amount,
                            ],
                            'quantity' => 1,
                        ]]
                    ],
                    'current_period_end' => now()->addMonth()->timestamp,
                ],
                'previous_attributes' => [
                    'status' => 'trialing'
                ]
            ],
        ];

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(200);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Convert->value,
        ]);

        $signal = Signal::query()->latest('id')->with(['dependencies'])->first();
        $this->assertSame('1000', $signal->amount_raw);
        $keepDep = $signal->dependencies->firstWhere('relation_type', 2);
        $this->assertSame(1, $keepDep->quantity);
    }

    public function test_trial_switch_signal()
    {
        $subscriptionId = 'sub_' . $this->faker->md5;

        $oldCharge = $this->plan->inclusions->first()->charge;
        $oldPrice = $oldCharge->twins->first();
        $oldPriceId = $oldPrice->reference_id;

        $newCharge = $this->higherPlan->inclusions->first()->charge;
        $newPrice = $newCharge->twins->first();
        $newPriceId = $newPrice->reference_id;

        $this->assertNotNull($newPriceId);
        $this->assertNotNull($oldPriceId);

        // Mock the Stripe customer response
        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
            [json_encode( [
                'object' => 'list',
                'url' => '/v1/subscriptions',
                'has_more' => false,
                'data' => [
                ]
            ]), 200, []],
        ]);

        $data = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'customer.subscription.updated',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'customer' => $this->customer->reference_id,
                    'status' => 'trialing',
                    'trial_end' => now()->addDays(12)->timestamp,
                    'items' => [
                        'object' => 'list',
                        'data' => [[
                            'price' => [
                                'id' => $newPriceId,
                                'unit_amount' => 2000
                            ],
                            'quantity' => 1,
                        ]]
                    ],
                ],
                'previous_attributes' => [
                    'items' => [
                        'object' => 'list',
                        'data' => [[
                            'price' => [
                                'id' => $oldPriceId,
                                'unit_amount' => 1000
                            ],
                            'quantity' => 1,
                        ]]
                    ]
                ]
            ]
        ];

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(200);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Switch->value,
        ]);
        $signal = Signal::query()->latest('id')->with(['dependencies'])->first();

        $this->assertSame('1000', $signal->amount_raw);
        $this->assertSame('USD', $signal->currency->getCode());
        $this->assertCount(2, $signal->dependencies);
    }

    public function test_upgrade_signal()
    {
        $subscriptionId = 'sub_' . $this->faker->md5;

        $oldCharge = $this->plan->inclusions->first()->charge;
        $oldPrice = $oldCharge->twins->first();
        $oldPriceId = $oldPrice->reference_id;

        $newCharge = $this->higherPlan->inclusions->first()->charge;
        $newPrice = $newCharge->twins->first();
        $newPriceId = $newPrice->reference_id;

        $this->assertNotNull($newPriceId);
        $this->assertNotNull($oldPriceId);

        // Mock the Stripe customer response
        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
            [json_encode( [
                'object' => 'list',
                'url' => '/v1/subscriptions',
                'has_more' => false,
                'data' => [
                ]
            ]), 200, []],
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
        ]);

        $data = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'customer.subscription.updated',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'customer' => $this->customer->reference_id,
                    'status' => 'active',
                    'current_period_end' => now()->addDay()->timestamp,
                    'items' => [
                        'object' => 'list',
                        'data' => [[
                            'price' => [
                                'id' => $newPriceId,
                                'unit_amount' => 2000,
                                'currency' => 'usd',
                            ],
                            'quantity' => 1,
                        ]]
                    ],
                ],
                'previous_attributes' => [
                    'items' => [
                        'object' => 'list',
                        'data' => [[
                            'price' => [
                                'id' => $oldPriceId,
                                'unit_amount' => 1000,
                                'currency' => 'usd',
                            ],
                            'quantity' => 1,
                        ]]
                    ]
                ]
            ]
        ];

        $flow = Flow::factory()
            ->for($this->org)
            ->create();
        $scenario = new Scenario();
        $scenario->flow_id = $flow->id;
        $scenario->display_name = 'skibidi toilet';
        $scenario->lookup_key = 'skibidi-toilet';
        $scenario->save();

        $collectorId = DB::table('stats_collectors')
            ->insertGetId([
                'uuid' => Str::uuid()->toString(),
                'client_id' => $this->org->liveClient()->id,
                'created_at' => now(),
            ]);

        Activity::unguard();
        Activity::query()
            ->create([
                'scenario_id' => $scenario->id,
                'customer_id' => $this->customer->id,
                'collector_id' => $collectorId,
                'last_interaction_at' => now(),
                'has_entered' => true,
                'client_id' => $this->org->liveClient()->id,
            ]);
        Activity::reguard();

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(200);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Upgrade->value,
        ]);

        $signal = Signal::query()->latest('id')->with(['dependencies'])->first();

        $this->assertSame(SignalID::Upgrade, $signal->type);
        $this->assertCount(2, $signal->dependencies);
        $fromDep = $signal->dependencies->firstWhere('relation_type', 0);
        $toDep = $signal->dependencies->firstWhere('relation_type', 1);
        $this->assertSame(1, $fromDep->quantity);
        $this->assertSame(0, intval($fromDep->delta));
        $this->assertSame(1, $toDep->quantity);
        $this->assertSame(0, intval($toDep->delta));

        $activity = $signal->activity;
        $this->assertNotNull($activity);

        $this->actingAs($this->user);
        $r = $this->getJson('/v1/intel/flows/'.$flow->getRouteKey().'/activities');
        $r->assertJsonPath('data.0.id', $activity->uuid);

        $s = $this->getJson('/v1/intel/flows/'.$flow->getRouteKey().'/scenarios');
        $s->assertJsonPath('data.0.stats.views_count.value', 1);


        $this->assertNotNull($signal->amount_raw);
        $this->assertSame('1000', $signal->amount_raw);
        $this->assertNotNull($signal->amount);
        $this->assertNotNull($signal->currency);
    }

    public function test_downgrade_signal()
    {
        $subscriptionId = 'sub_' . $this->faker->md5;

        $oldCharge = $this->higherPlan->inclusions->first()->charge;
        $oldPrice = $oldCharge->twins->first();
        $oldPriceId = $oldPrice->reference_id;

        $newCharge = $this->plan->inclusions->first()->charge;
        $newPrice = $newCharge->twins->first();
        $newPriceId = $newPrice->reference_id;

        $this->assertNotNull($newPriceId);
        $this->assertNotNull($oldPriceId);

        // Mock the Stripe customer response
        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
            [json_encode( [
                'object' => 'list',
                'url' => '/v1/subscriptions',
                'has_more' => false,
                'data' => [
                ]
            ]), 200, []],
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
        ]);

        $data = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'customer.subscription.updated',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'customer' => $this->customer->reference_id,
                    'status' => 'active',
                    'current_period_end' => now()->addDay()->timestamp,
                    'items' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'price' => [
                                    'id' => $newPriceId,
                                    'unit_amount' => 1000,
                                    'currency' => 'usd',
                                ],
                                'quantity' => 1,
                            ]
                        ],
                    ],
                ],
                'previous_attributes' => [
                    'items' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'price' => [
                                    'id' => $oldPriceId,
                                    'unit_amount' => 2000,
                                    'currency' => 'usd',
                                ],
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(200);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Downgrade->value,
        ]);

        $signal = Signal::query()->latest('id')->with(['dependencies'])->first();

        $this->assertSame(SignalID::Downgrade, $signal->type);
        $this->assertCount(2, $signal->dependencies);
        $fromDep = $signal->dependencies->firstWhere('relation_type', 0);
        $toDep = $signal->dependencies->firstWhere('relation_type', 1);
        $this->assertSame(1, $fromDep->quantity);
        $this->assertSame(0, intval($fromDep->delta));
        $this->assertSame(1, $toDep->quantity);
        $this->assertSame(0, intval($toDep->delta));

        $this->assertNotNull($signal->amount_raw);
        $this->assertSame('-1000', $signal->amount_raw);
        $this->assertNotNull($signal->amount);
        $this->assertNotNull($signal->currency);
    }

    public function test_expansion_signal()
    {
        $subscriptionId = 'sub_' . $this->faker->md5;

        $newCharge = $this->higherPlan->inclusions->first()->charge;
        $newPrice = $newCharge->twins->first();
        $newPriceId = $newPrice->reference_id;

        $this->assertNotNull($newPriceId);

        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
            [json_encode( [
                'object' => 'list',
                'url' => '/v1/subscriptions',
                'has_more' => false,
                'data' => [
                ]
            ]), 200, []],
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
        ]);

        $data = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'customer.subscription.updated',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'customer' => $this->customer->reference_id,
                    'status' => 'active',
                    'current_period_end' => now()->addDay()->timestamp,
                    'items' => [
                        'object' => 'list',
                        'data' => [[
                            'price' => [
                                'id' => $newPriceId,
                                'unit_amount' => 2000,
                                'currency' => 'usd',
                            ],
                            'quantity' => 2,
                        ]]
                    ],
                ],
                'previous_attributes' => [
                    'items' => [
                        'object' => 'list',
                        'data' => [[
                            'price' => [
                                'id' => $newPriceId,
                                'unit_amount' => 2000,
                                'currency' => 'usd',
                            ],
                            'quantity' => 1,
                        ]]
                    ]
                ]
            ]
        ];

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(200);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Expansion->value,
        ]);

        $signal = Signal::query()->latest('id')->with(['dependencies'])->first();

        $this->assertSame(SignalID::Expansion, $signal->type);
        $this->assertCount(1, $signal->dependencies);
        $keepDep = $signal->dependencies->firstWhere('relation_type', 2);
        $this->assertSame(2, $keepDep->quantity);
        $this->assertSame(1, intval($keepDep->delta));

        $this->assertNotNull($signal->amount_raw);
        $this->assertSame('2000', $signal->amount_raw);
        $this->assertNotNull($signal->amount);
        $this->assertNotNull($signal->currency);
    }

    public function test_contraction_signal()
    {
        $subscriptionId = 'sub_' . $this->faker->md5;

        $newCharge = $this->higherPlan->inclusions->first()->charge;
        $newPrice = $newCharge->twins->first();
        $newPriceId = $newPrice->reference_id;

        $this->assertNotNull($newPriceId);

        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
            [json_encode( [
                'object' => 'list',
                'url' => '/v1/subscriptions',
                'has_more' => false,
                'data' => [
                ]
            ]), 200, []],
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
        ]);

        $data = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'customer.subscription.updated',
            'livemode' => $this->billing->environment === 'live',
            'account' => $this->billing->lookup_key,
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'customer' => $this->customer->reference_id,
                    'status' => 'active',
                    'current_period_end' => now()->addDay()->timestamp,
                    'items' => [
                        'object' => 'list',
                        'data' => [[
                            'price' => [
                                'id' => $newPriceId,
                                'unit_amount' => 2000,
                                'currency' => 'usd',
                            ],
                            'quantity' => 1,
                        ]]
                    ],
                ],
                'previous_attributes' => [
                    'items' => [
                        'object' => 'list',
                        'data' => [[
                            'price' => [
                                'id' => $newPriceId,
                                'unit_amount' => 2000,
                                'currency' => 'usd',
                            ],
                            'quantity' => 2,
                        ]]
                    ]
                ]
            ]
        ];

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(200);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Contraction->value,
        ]);

        $signal = Signal::query()->latest('id')->with(['dependencies'])->first();

        $this->assertSame(SignalID::Contraction, $signal->type);
        $this->assertCount(1, $signal->dependencies);
        $keepDep = $signal->dependencies->firstWhere('relation_type', 2);
        $this->assertSame(1, $keepDep->quantity);
        $this->assertSame(-1, intval($keepDep->delta));

        $this->assertNotNull($signal->amount_raw);
        $this->assertSame('-2000', $signal->amount_raw);
        $this->assertNotNull($signal->amount);
        $this->assertNotNull($signal->currency);
    }

    public function test_cancel_signal()
    {
        $subscriptionId = 'sub_' . $this->faker->md5;

        // Get the base plan's charge and its associated price twin
        $charge = $this->plan->inclusions->first()->charge;
        $priceTwin = $charge->twins->first();

        // Mock the Stripe customer response
        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
            [json_encode( [
                'object' => 'list',
                'url' => '/v1/subscriptions',
                'has_more' => false,
                'data' => [
                ]
            ]), 200, []],
        ]);

        $data = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'customer.subscription.updated',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'customer' => $this->customer->reference_id,
                    'cancel_at_period_end' => true,
                    'current_period_end' => now()->addMonth()->timestamp,
                    'items' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'price' => [
                                    'id' => $priceTwin->reference_id,
                                    'unit_amount' => $priceTwin->data['unit_amount']
                                ],
                                'quantity' => 1,
                            ]
                        ]
                    ],
                ],
                'previous_attributes' => [
                    'cancel_at_period_end' => false,
                ]
            ]
        ];

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(200);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Cancel->value,
        ]);

        $signal = Signal::query()->latest('id')->with(['dependencies'])->first();
        $this->assertSame($this->customer->id, $signal->customer_id);
        $this->assertSame('-1000', $signal->amount_raw);
        $this->assertSame(SignalID::Cancel, $signal->type);
        $this->assertCount(1, $signal->dependencies);
        $keepDep = $signal->dependencies->first();
        $this->assertSame(2, $keepDep->relation_type); // keep
        $this->assertSame(1, $keepDep->quantity);
    }

    // todo: come back to this
    public function test_abandon_signal()
    {
        $subscriptionId = 'sub_' . $this->faker->md5;

        // Get the base plan's charge and its associated price twin
        $charge = $this->plan->inclusions->first()->charge;
        $priceTwin = $charge->twins->first();

        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
        ]);

        $data = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'customer.subscription.trial_will_end',
            'livemode' => $this->billing->environment === 'live',
            'account' => $this->billing->lookup_key,
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'customer' => $this->customer->reference_id,
                    'trial_end' => now()->addDays(3)->timestamp,
                    'default_payment_method' => null,
                    'items' => [
                        'object' => 'list',
                        'data' => [[
                            'price' => [
                                'id' => $priceTwin->reference_id,
                                'unit_amount' => $priceTwin->data['unit_amount']
                            ],
                            'quantity' => 1,
                        ]]
                    ],
                ]
            ]
        ];

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(200);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Abandon->value,
        ]);

        $signal = Signal::query()->latest('id')->with(['dependencies'])->first();
        $this->assertSame($this->customer->id, $signal->customer_id);
        $this->assertSame(null, $signal->amount_raw);
        $this->assertSame(SignalID::Abandon, $signal->type);
        $this->assertCount(1, $signal->dependencies);
        $keepDep = $signal->dependencies->first();
        $this->assertSame(2, $keepDep->relation_type); // keep
        $this->assertSame(1, $keepDep->quantity);
    }

    public function test_reactivate_signal()
    {
        Signal::unguard();
        // First create an abandon signal
        Signal::make(SignalID::Abandon)
            ->for($this->customer)
            ->emit();
        Signal::reguard();

        $subscriptionId = 'sub_' . $this->faker->md5;
        $charge = $this->plan->inclusions->first()->charge;
        $priceTwin = $charge->twins->first();

        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
            [json_encode(['object' => 'list', 'data' => []]), 200, []],
        ]);

        $data = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'customer.subscription.created',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'customer' => $this->customer->reference_id,
                    'status' => 'active',
                    'items' => [
                        'data' => [[
                            'price' => [
                                'id' => $priceTwin->reference_id,
                                'unit_amount' => 1000
                            ],
                            'quantity' => 1,
                        ]]
                    ],
                ]
            ]
        ];

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(200);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Reactivate->value,
        ]);

        $signal = Signal::query()->latest('id')->with(['dependencies'])->first();
//        dump($signal->toArray());
    }

    public function test_pause_resume_signals()
    {
        $subscriptionId = 'sub_' . $this->faker->md5;

        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
            [json_encode( [
                'object' => 'list',
                'url' => '/v1/subscriptions',
                'has_more' => false,
                'data' => [
                ]
            ]), 200, []],
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
            [json_encode( [
                'object' => 'list',
                'url' => '/v1/subscriptions',
                'has_more' => false,
                'data' => [
                ]
            ]), 200, []],
        ]);

        // Test pause
        $pauseData = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'customer.subscription.updated',
            'livemode' => $this->billing->environment === 'live',
            'account' => $this->billing->lookup_key,
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'customer' => $this->customer->reference_id,
                    'pause_collection' => [
                        'behavior' => 'void',
                        'resumes_at' => now()->addMonth()->timestamp,
                    ],
                ],
                'previous_attributes' => [
                    'pause_collection' => null,
                ]
            ]
        ];

        $response = $this->postStripeWebhookEvent($pauseData);
        $response->assertStatus(200);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Pause->value,
        ]);

        $signal = Signal::query()->latest('id')->with(['dependencies'])->first();
        $this->assertSame(SignalID::Pause, $signal->type);

        // Test resume
        $resumeData = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'customer.subscription.updated',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'customer' => $this->customer->reference_id,
                    'pause_collection' => null,
                ],
                'previous_attributes' => [
                    'pause_collection' => [
                        'behavior' => 'void',
                        'resumes_at' => now()->addMonth()->timestamp,
                    ],
                ]
            ]
        ];

        $response = $this->postStripeWebhookEvent($resumeData);
        $response->assertStatus(200);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Resume->value,
        ]);

        $signal = Signal::query()->latest('id')->with(['dependencies'])->first();
        $this->assertSame(SignalID::Resume, $signal->type);
//        dump($signal->toArray());
    }

//    public function test_order_signal()
//    {
//        $sessionId = 'cs_' . $this->faker->md5;
//
//        $data = [
//            'id' => 'evt_' . $this->faker->uuid,
//            'type' => 'checkout.session.completed',
//            'account' => $this->billing->lookup_key,
//            'data' => [
//                'object' => [
//                    'id' => $sessionId,
//                    'customer' => $this->customer->reference_id,
//                    'mode' => 'payment',
//                    'amount_total' => 2500,
//                ]
//            ]
//        ];
//
//        $response = $this->postStripeWebhookEvent($data);
//        $response->assertStatus(200);
//
//        $this->assertDatabaseHas('intel_signals', [
//            'customer_id' => $this->customer->id,
//            'type' => SignalID::Order->value,
//            'amount_raw' => '2500',
//        ]);
//    }

//    public function test_refund_signal()
//    {
//        $refundId = 're_' . $this->faker->md5;
//        $chargeId = 'ch_' . $this->faker->md5;
//
//        $charge = Charge::factory()->create();
//        Twin::factory()
//            ->for($this->billing, 'connector')
//            ->create([
//                'reference_id' => $chargeId,
//                'type' => \Stripe\Charge::class,
//            ])->linkable()->associate($charge);
//
//        $data = [
//            'id' => 'evt_' . $this->faker->uuid,
//            'type' => 'charge.refunded',
//            'account' => $this->billing->lookup_key,
//            'data' => [
//                'object' => [
//                    'id' => $refundId,
//                    'charge' => $chargeId,
//                    'customer' => $this->customer->reference_id,
//                    'amount' => 1000,
//                ]
//            ]
//        ];
//
//        $response = $this->postStripeWebhookEvent($data);
//        $response->assertStatus(200);
//
//        $this->assertDatabaseHas('intel_signals', [
//            'customer_id' => $this->customer->id,
//            'type' => SignalID::Return->value,
//            'amount_raw' => '1000',
//        ]);
//    }

    public function test_addon_signals()
    {
        $subscriptionId = 'sub_' . $this->faker->md5;

        $mainCharge = $this->plan->inclusions->first()->charge;
        $mainPrice = $mainCharge->twins->first();
        $mainPriceId = $mainPrice->reference_id;

        $addonCharge = $this->addonPlan->inclusions->first()->charge;
        $addonPrice = $addonCharge->twins->first();
        $addonPriceId = $addonPrice->reference_id;

        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
            [json_encode( [
                'object' => 'list',
                'url' => '/v1/subscriptions',
                'has_more' => false,
                'data' => [
                ]
                ]), 200, []],
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
            [json_encode( [
                'object' => 'list',
                'url' => '/v1/subscriptions',
                'has_more' => false,
                'data' => [
                ]
            ]), 200, []],
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
        ]);

        // Test attach
        $attachData = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'customer.subscription.updated',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'customer' => $this->customer->reference_id,
                    'current_period_end' => now()->addWeek()->timestamp,
                    'items' => [
                        'data' => [
                            [
                                'price' => [
                                    'id' => $mainPriceId,
                                ],
                                'quantity' => 1,
                            ],
                            [
                                'price' => [
                                    'id' => $addonPriceId,
                                ],
                                'quantity' => 1,
                            ],
                        ]
                    ],
                ],
                'previous_attributes' => [
                    'items' => [
                        'data' => [
                            [
                                'price' => [
                                    'id' => $mainPriceId,
                                ],
                                'quantity' => 1,
                            ],
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postStripeWebhookEvent($attachData);
        $response->assertStatus(200);

        $signal = Signal::query()->latest('id')->with(['dependencies'])->first();

        $this->assertSame(SignalID::Attach, $signal->type);
        $this->assertSame('500', $signal->amount_raw);
        $this->assertCount(1, $signal->dependencies);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Attach->value,
        ]);

        // Test detach
        $detachData = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'customer.subscription.updated',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'customer' => $this->customer->reference_id,
                    'current_period_end' => now()->addWeek()->timestamp,
                    'items' => [
                        'data' => [
                            [
                                'price' => [
                                    'id' => $mainPriceId,
                                ],
                                'quantity' => 1,
                            ],
                        ]
                    ],
                ],
                'previous_attributes' => [
                    'items' => [
                        'data' => [
                            [
                                'price' => [
                                    'id' => $mainPriceId,
                                ],
                                'quantity' => 1,
                            ],
                            [
                                'price' => [
                                    'id' => $addonPriceId,
                                ],
                                'quantity' => 1,
                            ],
                        ]
                    ]
                ]
            ]
        ];

        $response = $this->postStripeWebhookEvent($detachData);
//        dd($response->exception);
        $response->assertStatus(200);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Detach->value,
        ]);

        $signal = Signal::query()->latest('id')->with(['dependencies'])->first();

        $this->assertSame(SignalID::Detach, $signal->type);
        $this->assertSame('-500', $signal->amount_raw);
        $this->assertCount(1, $signal->dependencies);
    }

    public function test_payment_method_signal()
    {
        $paymentMethodId = 'pm_' . $this->faker->md5;

        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
        ]);

        $data = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'payment_method.attached',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $paymentMethodId,
                    'customer' => $this->customer->reference_id,
                    'type' => 'card',
                ]
            ]
        ];

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(200);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Preauthorize->value,
        ]);
    }

    public function test_renewal_signal()
    {
        $invoiceId = 'in_' . $this->faker->md5;
        $subscriptionId = 'sub_' . $this->faker->md5;

        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
        ]);

        $data = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'invoice.payment_succeeded',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $invoiceId,
                    'customer' => $this->customer->reference_id,
                    'subscription' => $subscriptionId,
                    'billing_reason' => 'subscription_cycle',
                    'amount_paid' => 1000,
                ]
            ]
        ];

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(200);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Renewal->value,
            'amount_raw' => '1000',
        ]);
    }

    public function test_invoicing_signal()
    {
        $invoiceId = 'in_' . $this->faker->md5;

        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($this->customer->reference_id)), 200, []],
        ]);

        $data = [
            'id' => 'evt_' . $this->faker->uuid,
            'type' => 'invoice.created',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $invoiceId,
                    'customer' => $this->customer->reference_id,
                    'billing_reason' => 'manual',
                    'amount_due' => 1500,
                ]
            ]
        ];


        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(200);

        $this->assertDatabaseHas('intel_signals', [
            'customer_id' => $this->customer->id,
            'type' => SignalID::Invoicing->value,
            'amount_raw' => '1500',
        ]);
    }

    protected function stripeCustomerResponse(string $customerId, array $merge = []): array
    {
        return array_merge([
            'id' => $customerId,
            'object' => 'customer',
            'account_balance' => 0,
            'address' => null,
            'balance' => 0,
            'created' => 1631530000,
            'currency' => null,
            'default_source' => null,
            'delinquent' => false,
            'description' => null,
            'discount' => null,
            'email' => $this->faker->email,
            'invoice_prefix' => 'F3E3E3E',
            'invoice_settings' => [
                'custom_fields' => null,
                'default_payment_method' => null,
                'footer' => null,
            ],
            'livemode' => $this->billing->environment === 'live',
            'metadata' => [],
            'name' => $this->faker->name,
            'phone' => null,
            'preferred_locales' => [],
            'shipping' => null,
            'tax_exempt' => 'none',
        ], $merge);
    }
}
