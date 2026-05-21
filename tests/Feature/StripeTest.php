<?php

namespace Tests\Feature;

use App\Models\Account\Customer;
use App\Models\Billing\BillingProvider;
use App\Models\Billing\Charge;
use App\Models\Catalog\Inclusion;
use App\Models\Convert\Checkout;
use App\Models\Management\Organization;
use App\Models\Pricing\Plan;
use App\Models\Store\Purchase;
use App\Models\Twin;
use App\Models\User;
use App\Services\Checkout\InitializePaymentMethodService;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Query;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Stripe\ApiRequestor;
use Stripe\Price;
use Stripe\Source;
use Tests\Helper\TestsAgainstStripe;
use Tests\TestCase;

class StripeTest extends TestCase
{
    use TestsAgainstStripe;

    protected BillingProvider $billing;

    protected Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = $this->createOrg();
        $this->billing = BillingProvider::factory()
            ->for($this->organization)
            ->stripe()
            ->create();
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    public function test_redirect_to_stripe()
    {
        $user = User::factory()->create();
        $user->organizations()->attach($this->organization->id);

        $this->actingAs($user);
        $res = $this->post('/v1/integrations/stripe/authorizations', [
            'redirect_uri' => 'https://example.com',
            'state' => $state = Str::random(32),
        ]);
        $url = $res->json('url');
        $this->assertStringContainsString('https://connect.stripe.com/oauth/authorize', $url);

        $uri = new Uri($url);
        $query = Query::parse($uri->getQuery());
        $this->assertSame('/oauth/authorize', $uri->getPath());
        $this->assertSame(config('services.stripe.client_id'), $query['client_id']);
        $this->assertSame(url('/integrations/stripe/callback'), $query['redirect_uri']);
        $this->assertSame('read_write', $query['scope']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame($this->organization->id, Cache::get($state)['organization_id']);
        $this->assertSame('https://example.com', Cache::get($state)['redirect_uri']);
    }

    public function test_redirect_from_stripe()
    {
        $user = User::factory()->create();
        $user->organizations()->attach($this->organization->id);
        $this->actingAs($user);

        $state = Str::random(32);

        Cache::put($state, [
            'organization_id' => $user->organization_id,
            'redirect_uri' => 'https://example.com',
        ], now()->addMinutes(30));

        $mock = $this->mock(Client::class);

        $mock->expects('post')
            ->andReturn(new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                'access_token' => $t = 'sk_test_'.Str::random(32),
                'stripe_user_id' => 'acct_'.Str::random(32),
                'refresh_token' => 'rt_'.Str::random(32),
                'token_type' => 'bearer',
                'scope' => 'read_write',
            ])));
        $mock->expects('get')
            ->andReturn(new \GuzzleHttp\Psr7\Response(200, [], json_encode([
                'id' => 'acct_'.Str::random(32),
                'object' => 'account',
            ])));

        Bus::fake();

        Socialite::driver('stripe')
            ->setHttpClient($mock);

        $query = Query::build([
            'state' => $state,
            'code' => 'ac_'.Str::random(32),
        ]);
        $res = $this->get('/integrations/stripe/callback?'.$query);
        $redirectUrl = $res->headers->get('Location');
        $this->assertNotNull($redirectUrl);
        $this->assertStringContainsString('https://example.com', $redirectUrl);
        $uri = new Uri($redirectUrl);
        $query = Query::parse($uri->getQuery());
        $this->assertSame('example.com', $uri->getHost());
        $connection = BillingProvider::retrieve($query['integration_id']);
        $this->assertNotNull($connection);
    }

    protected function stripeCustomerResponse(string $customerId, array $merge = [])
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
            'email' => fake()->email,
            'invoice_prefix' => 'F3E3E3E',
            'invoice_settings' => [
                'custom_fields' => null,
                'default_payment_method' => null,
                'footer' => null,
            ],
            'livemode' => $this->billing->environment === 'live',
            'metadata' => [],
            'name' => fake()->name,
            'phone' => null,
            'preferred_locales' => [],
            'shipping' => null,
            'tax_exempt' => 'none',
        ], $merge);
    }

    public function test_customer_created(): void
    {
        $data = [
            'id' => 'evt_'.fake()->uuid,
            'type' => 'customer.created',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $customerId = 'cus_J4fGz2eZvKYlo2',
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
                    'email' => $email = fake()->email,
                    'invoice_prefix' => 'F3E3E3E',
                    'invoice_settings' => [
                        'custom_fields' => null,
                        'default_payment_method' => null,
                        'footer' => null,
                    ],
                    'livemode' => $this->billing->environment === 'live',
                    'metadata' => [],
                    'name' => $name = fake()->name,
                    'phone' => null,
                    'preferred_locales' => [],
                    'shipping' => null,
                    'tax_exempt' => 'none',
                ],
            ],
        ];

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(Response::HTTP_OK);

        $twin = $this->billing
            ->twins()
            ->where('reference_id', $customerId)
            ->first();
        $this->assertSame($this->organization->id, $twin->organization_id);
        $this->assertSame($this->billing->id, $twin->connector_id);
        $this->assertSame(\Stripe\Customer::class, $twin->type);
        $this->assertSame($customerId, $twin->reference_id);
        $this->assertSame($data['data']['object']['created'], $twin->reference_created_at->timestamp);

        /* @var Customer $customer */
        $customer = Customer::query()
            ->where([
                'organization_id' => $this->organization->id,
                'reference_id' => $customerId,
            ])
            ->first();

        $this->assertNotNull($customer);
        $this->assertSame($data['data']['object']['email'], $customer->email);
        $this->assertSame($data['data']['object']['name'], $customer->name);
        $this->assertSame($twin->reference_id, $customer->reference_id);
        $this->assertSame($twin->organization_id, $customer->organization_id);

        $this->assertNotEmpty($twin->linkable);
    }

    public function test_customer_updated()
    {
        $data = [
            'id' => 'evt_'.fake()->uuid,
            'type' => 'customer.updated',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $customerId = 'cus_J4fGz2eZvKYlo2',
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
                    'email' => $email = fake()->email,
                    'invoice_prefix' => 'F3E3E3E',
                    'invoice_settings' => [
                        'custom_fields' => null,
                        'default_payment_method' => null,
                        'footer' => null,
                    ],
                    'livemode' => $this->billing->environment === 'live',
                    'metadata' => [],
                    'name' => $name = fake()->name,
                    'phone' => null,
                    'preferred_locales' => [],
                    'shipping' => null,
                    'tax_exempt' => 'none',
                ],
            ],
        ];

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(Response::HTTP_OK);

        $twin = $this->billing
            ->twins()
            ->where('reference_id', $customerId)
            ->first();
        $this->assertSame($this->organization->id, $twin->organization_id);
        $this->assertSame($this->billing->id, $twin->connector_id);
        $this->assertSame(\Stripe\Customer::class, $twin->type);
        $this->assertSame($customerId, $twin->reference_id);
        $this->assertSame($data['data']['object']['created'], $twin->reference_created_at->timestamp);

        /* @var Customer $customer */
        $customer = Customer::query()
            ->where([
                'organization_id' => $this->organization->id,
                'reference_id' => $customerId,
            ])
            ->first();

        $this->assertNotNull($customer);
        $this->assertSame($data['data']['object']['email'], $customer->email);
    }

    public function test_customer_deleted()
    {
        $data = [
            'id' => 'evt_'.fake()->uuid,
            'type' => 'customer.deleted',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $customerId = 'cus_J4fGz2eZvKYlo2',
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
                    'email' => $email = fake()->email,
                    'invoice_prefix' => 'F3E3E3E',
                    'invoice_settings' => [
                        'custom_fields' => null,
                        'default_payment_method' => null,
                        'footer' => null,
                    ],
                    'livemode' => $this->billing->environment === 'live',
                    'metadata' => [],
                    'name' => $name = fake()->name,
                    'phone' => null,
                    'preferred_locales' => [],
                    'shipping' => null,
                    'tax_exempt' => 'none',
                ],
            ],
        ];

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(Response::HTTP_OK);

        $twin = $this->billing
            ->twins()
            ->where('reference_id', $customerId)
            ->first();
        $this->assertSame($this->organization->id, $twin->organization_id);
        $this->assertSame($this->billing->id, $twin->connector_id);
        $this->assertSame(\Stripe\Customer::class, $twin->type);
        $this->assertSame($customerId, $twin->reference_id);
        $this->assertSame($data['data']['object']['created'], $twin->reference_created_at->timestamp);

        /* @var Customer $customer */
        $customer = Customer::query()
            ->where([
                'organization_id' => $this->organization->id,
                'reference_id' => $customerId,
            ])
            ->withTrashed()
            ->first();

        $this->assertNotNull($customer);
        $this->assertSame($data['data']['object']['email'], $customer->email);
        $this->assertTrue($customer->trashed());
    }

    public function test_customer_source_created()
    {
        $data = [
            'id' => 'evt_'.fake()->uuid,
            'type' => 'customer.source.created',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $sourceId = 'src_'.Str::random(16),
                    'object' => 'source',
                    'ach_credit_transfer' => null,
                    'ach_debit' => null,
                    'acss_debit' => null,
                    'alipay' => null,
                    'amount' => null,
                    'bancontact' => null,
                    'card' => [
                        'brand' => 'Visa',
                        'checks' => [
                            'address_line1_check' => null,
                            'address_postal_code_check' => null,
                            'cvc_check' => null,
                        ],
                        'country' => 'US',
                        'currency' => 'usd',
                        'customer' => $customerId = 'cus_J4fGz2eZvKYlo2',
                        'cvc_check' => null,
                        'dynamic_last4' => null,
                        'exp_month' => 12,
                        'exp_year' => 2022,
                        'fingerprint' => 'J4fGz2eZvKYlo2',
                        'funding' => 'credit',
                        'iin' => null,
                        'issuer' => null,
                        'last4' => '4242',
                        'network' => 'visa',
                        'three_d_secure' => null,
                        'wallet' => null,
                    ],
                    'card_present' => null,
                    'created' => 1631530000,
                    'currency' => 'usd',
                    'customer' => $customerId,
                    'eps' => null,
                    'fpx' => null,
                    'giropay' => null,
                    'ideal' => null,
                    'interac_present' => null,
                    'klarna' => null,
                    'livemode' => $this->billing->environment === 'live',
                    'multibanco' => null,
                    'p24' => null,
                    'sepa_credit_transfer' => null,
                    'sepa_debit' => null,
                    'sofort' => null,
                    'three_d_secure' => null,
                    'type' => 'card',
                    'usage' => 'reusable',
                ],
            ],
        ];

        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($customerId)), 200, []],
        ]);

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(Response::HTTP_OK);

        $twin = $this->billing
            ->twins()
            ->where('reference_id', $sourceId)
            ->first();

        $this->assertSame($this->organization->id, $twin->organization_id);
        $this->assertSame($this->billing->id, $twin->connector_id);
        $this->assertSame(Source::class, $twin->type);
        $this->assertSame($sourceId, $twin->reference_id);
        $this->assertSame($data['data']['object']['created'], $twin->reference_created_at->timestamp);

    }

    public function test_customer_source_deleted()
    {
        $data = [
            'id' => 'evt_'.fake()->uuid,
            'type' => 'customer.source.deleted',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $sourceId = 'src_'.Str::random(16),
                    'object' => 'source',
                    'ach_credit_transfer' => null,
                    'ach_debit' => null,
                    'acss_debit' => null,
                    'alipay' => null,
                    'amount' => null,
                    'bancontact' => null,
                    'card' => [
                        'brand' => 'Visa',
                        'checks' => [
                            'address_line1_check' => null,
                            'address_postal_code_check' => null,
                            'cvc_check' => null,
                        ],
                        'country' => 'US',
                        'currency' => 'usd',
                        'customer' => $customerId = 'cus_J4fGz2eZvKYlo2',
                        'cvc_check' => null,
                        'dynamic_last4' => null,
                        'exp_month' => 12,
                        'exp_year' => 2022,
                        'fingerprint' => 'J4fGz2eZvKYlo2',
                        'funding' => 'credit',
                        'iin' => null,
                        'issuer' => null,
                        'last4' => '4242',
                        'network' => 'visa',
                        'three_d_secure' => null,
                        'wallet' => null,
                    ],
                    'card_present' => null,
                    'created' => 1631530000,
                    'currency' => 'usd',
                    'customer' => $customerId,
                    'eps' => null,
                    'fpx' => null,
                    'giropay' => null,
                    'ideal' => null,
                    'interac_present' => null,
                    'klarna' => null,
                    'livemode' => $this->billing->environment === 'live',
                    'multibanco' => null,
                    'p24' => null,
                    'sepa_credit_transfer' => null,
                    'sepa_debit' => null,
                    'sofort' => null,
                    'three_d_secure' => null,
                    'type' => 'card',
                    'usage' => 'reusable',
                ],
            ],
        ];

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(Response::HTTP_OK);

        $twin = $this->billing
            ->twins()
            ->where('reference_id', $sourceId)
            ->first();

        $this->assertNull($twin);
    }

    public function test_subscription_created()
    {
        $subscriptionId = 'sub_'.Str::random(12);
        $customerId = 'cus_'.Str::random(12);
        $priceId = 'price_'.Str::random(12);

        $data = [
            'id' => 'evt_'.fake()->uuid,
            'type' => 'customer.subscription.created',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'object' => 'subscription',
                    'application_fee_percent' => null,
                    'billing_cycle_anchor' => 1631530000,
                    'billing_thresholds' => null,
                    'cancel_at' => null,
                    'cancel_at_period_end' => false,
                    'canceled_at' => null,
                    'collection_method' => 'charge_automatically',
                    'created' => 1631530000,
                    'current_period_end' => 1631530000,
                    'current_period_start' => 1631530000,
                    'customer' => $customerId,
                    'days_until_due' => null,
                    'default_payment_method' => null,
                    'default_source' => null,
                    'default_tax_rates' => [],
                    'discount' => null,
                    'ended_at' => null,
                    'items' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'id' => 'si_J4fGz2eZvKYlo2',
                                'object' => 'subscription_item',
                                'billing_thresholds' => null,
                                'created' => 1631530000,
                                'metadata' => [],
                                'price' => [
                                    'id' => $priceId,
                                ],
                                'quantity' => 1,
                                'subscription' => $subscriptionId,
                                'tax_rates' => [],
                            ],
                        ],
                        'has_more' => false,
                        'total_count' => 1,
                        'url' => '/v1/subscription_items?subscription=sub_J4fGz2eZvKYlo2',
                    ],
                    'latest_invoice' => 'in_J4fGv',
                    'livemode' => $this->billing->environment === 'live',
                    'metadata' => [],
                    'next_pending_invoice_item_invoice' => null,
                    'pause_collection' => null,
                    'pending_invoice_item_interval' => null,
                    'pending' => null,
                    'pending_setup_intent' => null,
                    'pending_update' => null,
                    'plan' => [
                        'id' => $planId = 'plan_J4fGz2eZvKYlo2',
                        'object' => 'plan',
                        'active' => true,
                        'aggregate_usage' => null,
                        'amount' => 1000,
                        'amount_decimal' => '1000',
                        'billing_scheme' => 'per_unit',
                        'created' => 1631530000,
                        'currency' => 'usd',
                        'interval' => 'month',
                        'interval_count' => 1,
                        'livemode' => $this->billing->environment === 'live',
                        'metadata' => [],
                        'nickname' => null,
                        'product' => $productId = 'prod_J4fGz2eZvKYlo2',
                        'tiers' => null,
                        'tiers_mode' => null,
                        'transform_usage' => null,
                        'trial_period_days' => null,
                        'usage_type' => 'licensed',
                    ],
                    'quantity' => 1,
                    'schedule' => null,
                    'start_date' => 1631530000,
                    'status' => 'active',
                    'tax_percent' => null,
                    'trial_end' => null,
                    'trial_start' => null,
                ],
            ],
        ];

        $charge = Charge::factory()
            ->for($this->organization)
            ->create([
                'product_id' => 1,
            ]);
        $chargeTwin = Twin::factory()
            ->for($this->organization)
            ->for($this->billing, 'connector')
            ->create([
                'type' => Price::class,
                'reference_id' => $priceId,
                'data' => [],
            ]);
        $chargeTwin->linkable()->associate($charge);
        $chargeTwin->save();
        /* @var Plan $plan */
        $plan = Plan::factory()
            ->for($this->organization)
            ->create([
            ]);
        Inclusion::factory()
            ->for($charge, 'charge')
            ->for($plan)
            ->create();

        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($customerId)), 200, []],
            [json_encode($this->stripeAllSubscriptionResponse($priceId)), 200, []],
        ]);

        $response = $this->postStripeWebhookEvent($data);
        $response->assertStatus(Response::HTTP_OK);

        $twin = $this->billing
            ->twins()
            ->where('reference_id', $subscriptionId)
            ->first();

        $customer = Customer::query()
            ->where([
                'organization_id' => $this->organization->id,
                'reference_id' => $customerId,
            ])
            ->first();
        $this->assertNotNull($customer);
        $schedule = $customer->schedule;
        $this->assertNotNull($schedule);

        $subs = $customer->subscriptions;
        $this->assertCount(1, $subs);
    }

    protected function stripeAllSubscriptionResponse(string|null $priceId = null)
    {
        $priceId ??= 'price_'.Str::random(12);

        return [
            'object' => 'list',
            'url' => '/v1/subscriptions',
            'has_more' => false,
            'data' => [
                [
                    'id' => 'sub_1MowQVLkdIwHu7ixeRlqHVzs',
                    'object' => 'subscription',
                    'application' => null,
                    'application_fee_percent' => null,
                    'automatic_tax' => ['enabled' => false, 'liability' => null],
                    'billing_cycle_anchor' => 1679609767,
                    'billing_thresholds' => null,
                    'cancel_at' => null,
                    'cancel_at_period_end' => false,
                    'canceled_at' => null,
                    'cancellation_details' => [
                        'comment' => null,
                        'feedback' => null,
                        'reason' => null,
                    ],
                    'collection_method' => 'charge_automatically',
                    'created' => 1679609767,
                    'currency' => 'usd',
                    'current_period_end' => 1682288167,
                    'current_period_start' => 1679609767,
                    'customer' => 'cus_Na6dX7aXxi11N4',
                    'days_until_due' => null,
                    'default_payment_method' => null,
                    'default_source' => null,
                    'default_tax_rates' => [],
                    'description' => null,
                    'discount' => null,
                    'discounts' => null,
                    'ended_at' => null,
                    'invoice_settings' => ['issuer' => ['type' => 'self']],
                    'items' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'id' => 'si_Na6dzxczY5fwHx',
                                'object' => 'subscription_item',
                                'billing_thresholds' => null,
                                'created' => 1679609768,
                                'metadata' => [],
                                'plan' => [
                                    'id' => $priceId,
                                    'object' => 'plan',
                                    'active' => true,
                                    'aggregate_usage' => null,
                                    'amount' => 1000,
                                    'amount_decimal' => '1000',
                                    'billing_scheme' => 'per_unit',
                                    'created' => 1679609766,
                                    'currency' => 'usd',
                                    'discounts' => null,
                                    'interval' => 'month',
                                    'interval_count' => 1,
                                    'livemode' => false,
                                    'metadata' => [],
                                    'nickname' => null,
                                    'product' => 'prod_Na6dGcTsmU0I4R',
                                    'tiers_mode' => null,
                                    'transform_usage' => null,
                                    'trial_period_days' => null,
                                    'usage_type' => 'licensed',
                                ],
                                'price' => [
                                    'id' => $priceId,
                                    'object' => 'price',
                                    'active' => true,
                                    'billing_scheme' => 'per_unit',
                                    'created' => 1679609766,
                                    'currency' => 'usd',
                                    'custom_unit_amount' => null,
                                    'livemode' => false,
                                    'lookup_key' => null,
                                    'metadata' => [],
                                    'nickname' => null,
                                    'product' => 'prod_Na6dGcTsmU0I4R',
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
                                ],
                                'quantity' => 1,
                                'subscription' => 'sub_1MowQVLkdIwHu7ixeRlqHVzs',
                                'tax_rates' => [],
                            ],
                        ],
                        'has_more' => false,
                        'total_count' => 1,
                        'url' => '/v1/subscription_items?subscription=sub_1MowQVLkdIwHu7ixeRlqHVzs',
                    ],
                    'latest_invoice' => 'in_1MowQWLkdIwHu7ixuzkSPfKd',
                    'livemode' => false,
                    'metadata' => [],
                    'next_pending_invoice_item_invoice' => null,
                    'on_behalf_of' => null,
                    'pause_collection' => null,
                    'payment_settings' => [
                        'payment_method_options' => null,
                        'payment_method_types' => null,
                        'save_default_payment_method' => 'off',
                    ],
                    'pending_invoice_item_interval' => null,
                    'pending_setup_intent' => null,
                    'pending_update' => null,
                    'schedule' => null,
                    'start_date' => 1679609767,
                    'status' => 'active',
                    'test_clock' => null,
                    'transfer_data' => null,
                    'trial_end' => null,
                    'trial_settings' => [
                        'end_behavior' => [
                            'missing_payment_method' => 'create_invoice',
                        ],
                    ],
                    'trial_start' => null,
                ],
            ],
        ];
    }

    protected function stripeProductResponse(string $productId)
    {
        return [
            'id' => $productId,
            'object' => 'product',
            'active' => true,
            'attributes' => [],
            'caption' => null,
            'created' => 1631530000,
            'deactivate_on' => [],
            'description' => null,
            'images' => [],
            'livemode' => $this->billing->environment === 'live',
            'metadata' => [],
            'name' => 'Test Product',
            'package_dimensions' => null,
            'shippable' => null,
            'statement_descriptor' => null,
            'type' => 'service',
            'unit_label' => null,
            'updated' => 1631530000,
            'url' => null,
        ];
    }

    protected function stripePriceResponse(string $priceId, string $productId)
    {
        return [
            'id' => $priceId,
            'object' => 'price',
            'active' => true,
            'billing_scheme' => 'per_unit',
            'created' => 1631530000,
            'currency' => 'usd',
            'livemode' => $this->billing->environment === 'live',
            'lookup_key' => null,
            'metadata' => [],
            'nickname' => null,
            'product' => $productId,
            'recurring' => [
                'aggregate_usage' => null,
                'interval' => 'month',
                'interval_count' => 1,
                'trial_period_days' => null,
                'usage_type' => 'licensed',
            ],
            'tiers' => null,
            'tiers_mode' => null,
            'transform_quantity' => null,
            'type' => 'recurring',
            'unit_amount' => 1000,
            'unit_amount_decimal' => '1000',
        ];
    }

    public function test_customer_subscription_deleted()
    {
        $this->markTestIncomplete();
        $subscriptionId = 'sub_'.Str::random(12);
        $customerId = 'cus_'.Str::random(12);

        $data = [
            'id' => 'evt_'.fake()->uuid,
            'type' => 'customer.subscription.deleted',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'object' => 'subscription',
                    'application_fee_percent' => null,
                    'billing_cycle_anchor' => 1631530000,
                    'billing_thresholds' => null,
                    'cancel_at' => null,
                    'cancel_at_period_end' => false,
                    'canceled_at' => null,
                    'collection_method' => 'charge_automatically',
                    'created' => 1631530000,
                    'current_period_end' => 1631530000,
                    'current_period_start' => 1631530000,
                    'customer' => $customerId,
                    'days_until_due' => null,
                    'default_payment_method' => null,
                    'default_source' => null,
                    'default_tax_rates' => [],
                    'discount' => null,
                    'ended_at' => null,
                    'items' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'id' => 'si_J4fGz2eZvKYlo2',
                                'object' => 'subscription_item',
                                'billing_thresholds' => null,
                                'created' => 1631530000,
                                'metadata' => [],
                                'price' => $priceId = 'price_J4fGz2eZvKYlo2',
                                'quantity' => 1,
                                'subscription' => $subscriptionId,
                                'tax_rates' => [],
                            ],
                        ],
                        'has_more' => false,
                        'total_count' => 1,
                        'url' => '/v1/subscription_items?subscription=sub_J4fGz2eZvKYlo2',
                    ],
                    'latest_invoice' => 'in_J4fGv',
                    'livemode' => $this->billing->environment === 'live',
                    'metadata' => [],
                    'next_pending_invoice_item_invoice' => null,
                    'pause_collection' => null,
                    'pending_invoice_item_interval' => null,
                    'pending' => null,
                    'pending_setup_intent' => null,
                    'pending_update' => null,
                    'plan' => [
                        'id' => $planId = 'plan_J4fGz2eZvKYlo2',
                        'object' => 'plan',
                        'active' => true,
                        'aggregate_usage' => null,
                        'amount' => 1000,
                        'amount_decimal' => '1000',
                        'billing_scheme' => 'per_unit',
                        'created' => 1631530000,
                        'currency' => 'usd',
                        'interval' => 'month',
                        'interval_count' => 1,
                        'livemode' => $this->billing->environment === 'live',
                        'metadata' => [],
                        'nickname' => null,
                        'product' => $productId = 'prod_J4fGz2eZvKYlo2',
                        'tiers' => null,
                        'tiers_mode' => null,
                        'transform_usage' => null,
                        'trial_period_days' => null,
                        'usage_type' => 'licensed',
                    ],
                    'quantity' => 1,
                    'schedule' => null,
                    'start_date' => 1631530000,
                    'status' => 'active',
                    'tax_percent' => null,
                    'trial_end' => null,
                    'trial_start' => null,
                ],
            ],
        ];

        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($customerId)), 200, []],
        ]);

        $response = $this->postStripeWebhookEvent($data);

        $response->assertStatus(Response::HTTP_OK);

        $twin = $this->billing
            ->twins()
            ->where('reference_id', $subscriptionId)
            ->withTrashed()
            ->first();

        //        $this->assertTrue($twin->trashed());
    }

    public function test_validation_on_bad_pm()
    {
        $client = $this->organization->clients()->first();
        $agent = \App\Models\Account\Agent::factory()
            ->for($this->organization)
            ->create();
        $customer = Customer::factory()
            ->for($this->organization)
            ->create();
        $bsp = $this->billing;
        $purchase = Purchase::factory()
            ->for($this->organization)
            ->for($bsp, 'billing_provider')
            ->create([
                'customer_id' => $customer->id,
                'provider_id' => Str::random(32),
                'provider_name' => 'plandalf',
                'currency' => 'USD',
                'intent' => 'upgrade',
                'current_state' => 'started',
            ]);
        $init = new InitializePaymentMethodService($client, $purchase);

        $data = [
            'props' => [
                'confirmation_token' => Str::random(16),
            ],
        ];

        $this->mockStripe([
            // mock confirmation token retrieve
            [json_encode($this->stripeConfirmationTokenResponse()), 200, []],
            [json_encode($this->stripeFailedSetupIntentResponse()), 402, []],
        ]);

        // see that $init throws a validation exception
        $this->expectException(ValidationException::class);
        $init($data);
    }

    // paused
    public function test_customer_subscription_updated()
    {
        $subscriptionId = 'sub_'.Str::random(12);
        $customerId = 'cus_'.Str::random(12);
        $data = [
            'id' => 'evt_'.fake()->uuid,
            'type' => 'customer.subscription.updated',
            'account' => $this->billing->lookup_key,
            'livemode' => $this->billing->environment === 'live',
            'data' => [
                'object' => [
                    'id' => $subscriptionId,
                    'object' => 'subscription',
                    'application_fee_percent' => null,
                    'billing_cycle_anchor' => 1631530000,
                    'billing_thresholds' => null,
                    'cancel_at' => null,
                    'cancel_at_period_end' => false,
                    'canceled_at' => null,
                    'collection_method' => 'charge_automatically',
                    'created' => 1631530000,
                    'current_period_end' => 1631530000,
                    'current_period_start' => 1631530000,
                    'customer' => $customerId,
                    'days_until_due' => null,
                    'default_payment_method' => null,
                    'default_source' => null,
                    'default_tax_rates' => [],
                    'discount' => null,
                    'ended_at' => null,
                    'items' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'id' => 'si_J4fGz2eZvKYlo2',
                                'object' => 'subscription_item',
                                'billing_thresholds' => null,
                                'created' => 1631530000,
                                'metadata' => [],
                                'price' => $priceId = 'price_J4fGz2eZvKYlo2',
                                'quantity' => 1,
                                'subscription' => $subscriptionId,
                                'tax_rates' => [],
                            ],
                        ],
                        'has_more' => false,
                        'total_count' => 1,
                        'url' => '/v1/subscription_items?subscription=sub_J4fGz2eZvKYlo2',
                    ],
                    'latest_invoice' => 'in_J4fGv',
                    'livemode' => $this->billing->environment === 'live',
                    'metadata' => [],
                    'next_pending_invoice_item_invoice' => null,
                    'pause_collection' => null,
                    'pending_invoice_item_interval' => null,
                    'pending' => null,
                    'pending_setup_intent' => null,
                    'pending_update' => null,
                    'plan' => [
                        'id' => $planId = 'plan_J4fGz2eZvKYlo2',
                        'object' => 'plan',
                        'active' => true,
                        'aggregate_usage' => null,
                        'amount' => 1000,
                        'amount_decimal' => '1000',
                        'billing_scheme' => 'per_unit',
                        'created' => 1631530000,
                        'currency' => 'usd',
                        'interval' => 'month',
                        'interval_count' => 1,
                        'livemode' => $this->billing->environment === 'live',
                        'metadata' => [],
                        'nickname' => null,
                        'product' => $productId = 'prod_J4fGz2eZvKYlo2',
                        'tiers' => null,
                        'tiers_mode' => null,
                        'transform_usage' => null,
                        'trial_period_days' => null,
                        'usage_type' => 'licensed',
                    ],
                    'quantity' => 1,
                    'schedule' => null,
                    'start_date' => 1631530000,
                    'status' => 'active',
                    'tax_percent' => null,
                    'trial_end' => null,
                    'trial_start' => null,
                ],
            ],
        ];

        $this->mockStripe([
            [json_encode($this->stripeCustomerResponse($customerId)), 200, []],
            [json_encode($this->stripeAllSubscriptionResponse()), 200, []],
        ]);

        $response = $this->postStripeWebhookEvent($data);

        $response->assertStatus(Response::HTTP_OK);

        $twin = $this->billing
            ->twins()
            ->where('reference_id', $subscriptionId)
            ->first();
        $this->assertNotNull($twin);

        $customer = Customer::query()
            ->where([
                'organization_id' => $this->organization->id,
                'reference_id' => $customerId,
            ])
            ->first();
        //        dump($customer->toArray());
        $this->assertNotNull($customer);

        $schedule = $customer->schedule;
        $this->assertNotNull($schedule);

        //        $this->assertCount(1, $schedule->plans);
    }

    private function stripeConfirmationTokenResponse()
    {
        return [
            'id' => 'ctoken_1NnQUf2eZvKYlo2CIObdtbnb',
            'object' => 'confirmation_token',
            'created' => 1694025025,
            'expires_at' => 1694068225,
            'livemode' => true,
            'mandate_data' => null,
            'payment_intent' => null,
            'payment_method' => null,
            'payment_method_preview' => [
                'billing_details' => [
                    'address' => [
                        'city' => 'Hyde Park',
                        'country' => 'US',
                        'line1' => '50 Sprague St',
                        'line2' => '',
                        'postal_code' => '02136',
                        'state' => 'MA',
                    ],
                    'email' => 'jennyrosen@stripe.com',
                    'name' => 'Jenny Rosen',
                    'phone' => null,
                ],
                'card' => [
                    'brand' => 'visa',
                    'checks' => [
                        'address_line1_check' => null,
                        'address_postal_code_check' => null,
                        'cvc_check' => null,
                    ],
                    'country' => 'US',
                    'display_brand' => 'visa',
                    'exp_month' => 8,
                    'exp_year' => 2026,
                    'funding' => 'credit',
                    'generated_from' => null,
                    'last4' => '4242',
                    'networks' => [
                        'available' => ['visa'],
                        'preferred' => null,
                    ],
                    'three_d_secure_usage' => [
                        'supported' => true,
                    ],
                    'wallet' => null,
                ],
                'type' => 'card',
            ],
            'return_url' => 'https://example.com/return',
            'setup_future_usage' => 'off_session',
            'setup_intent' => null,
            'shipping' => [
                'address' => [
                    'city' => 'Hyde Park',
                    'country' => 'US',
                    'line1' => '50 Sprague St',
                    'line2' => '',
                    'postal_code' => '02136',
                    'state' => 'MA',
                ],
                'name' => 'Jenny Rosen',
                'phone' => null,
            ],
        ];
    }

    private function stripeFailedSetupIntentResponse()
    {
        return [
            'error' => [
                'message' => 'Your card was declined.',
                'type' => 'card_error',
                'code' => 'card_declined',
                'decline_code' => 'generic_decline',
            ],
        ];
    }

    public function test_primary_payment_method()
    {
        $methodsResponse = $this->makeStripePaymentMethodList();

        $pmId = Arr::get($methodsResponse, 'data.0.id');
        $this->assertNotNull($pmId);

        $customer = Customer::factory()
            ->for($this->organization)
            ->for($this->billing)
            ->create([
            ]);
        $extras = [
            'invoice_settings' => [
                'custom_fields' => null,
                'default_payment_method' => $pmId,
                'footer' => null,
            ],
        ];
        $customersResponse = $this->stripeCustomerResponse($customer->reference_id, $extras);

        $customerTwin = Twin::query()
            ->updateOrCreate([
                'reference_id' => $customer->reference_id,
                'connector_id' => $this->billing->id,
                'type' => \Stripe\Customer::class,
                'connector_type' => 'billing_provider',
                'organization_id' => $this->organization->id,
            ], Twin::fromStripeObject(\Stripe\Customer::constructFrom($customersResponse))->toArray());
        $customerTwin->linkable()->associate($customer);
        $customerTwin->save();

        $this->mockStripe([
            [json_encode($customersResponse), 200, []],
            [json_encode($methodsResponse), 200, []],
        ]);

        $customer->syncCustomer();
        $customer->refresh();

        $this->assertNotNull($customer->primary_payment_method_id);
        $this->assertNotNull($customer->primaryPaymentMethod);
        $this->assertSame($pmId, $customer->primaryPaymentMethod->reference_id);

        $this->assertNotNull($customer->primaryPaymentMethod->reference_id);

        $client = $this->organization->clients->first();

        $res = $this->getJson('/client/session', [
            'Authorization' => 'Bearer '.$client->generateAgentJwt('test', [
                'customer' => $customer->reference_id,
            ]),
        ]);

        $res->assertStatus(200);
    }
}
