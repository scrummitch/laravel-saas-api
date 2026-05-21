<?php

namespace Tests\Feature;

use App\Models\Billing\BillingProvider;
use App\Models\Management\Organization;
use App\Models\Twin;
use App\Services\Billing\ProviderInitialSyncService;
use Illuminate\Support\Testing\Fakes\BatchFake;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\Product;
use Tests\TestCase;

class ConnectionTest extends TestCase
{
    public function test_create_connection(): void
    {
        $org = Organization::factory()
            ->create();

        /* @var BillingProvider $billing */
        $billing = BillingProvider::factory()
            ->for($org)
            ->stripe()
            ->create();

        $this->assertNotNull($billing->id);
        $this->assertSame('stripe', $billing->type);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient
            ->expects($this->atLeastOnce())
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                [json_encode($this->stripeCustomers()), 200, []],
                [json_encode($this->stripeSubscriptions()), 200, []],
                [json_encode($this->stripeProducts()), 200, []],
                [json_encode($this->stripePrices()), 200, []],
                [json_encode($this->stripeCoupon()), 200, []],
                [json_encode($this->stripeCustomers()), 200, []],
                [json_encode($this->stripeSubscriptions()), 200, []],
                [json_encode($this->stripeSubscriptions()), 200, []],
            );

        ApiRequestor::setHttpClient($httpClient);

        $service = new ProviderInitialSyncService;
        $service($billing, false);

        $productTwins = Twin::query()
            ->where(['connector_id' => $billing->getKey(), 'type' => Product::class])
            ->get();
        $this->assertCount(1, $productTwins);
        $this->assertCount(1, $org->products);
        $this->assertCount(1, $org->charges);
        $this->assertCount(1, $org->plans);
        $this->assertCount(1, $org->customers);
        $this->assertCount(1, $org->schedules);

        $schedule = $org->schedules->first();
        $customer = $org->customers->first();
        $this->assertSame($schedule->customer_id, $customer->id);
        $this->assertCount(1, $schedule->plans);

        $operation = $org
            ->operations()
            ->latest()
            ->first();

        $this->assertNotNull($operation);
    }

    private function stripeProducts()
    {
        return [
            'object' => 'list',
            'data' => [
                [
                    'id' => 'prod_E2qo3Zt2eZvKYlo2',
                    'object' => 'product',
                    'active' => true,
                    'attributes' => [],
                    'created' => 1553206699,
                    'description' => null,
                    'images' => [],
                    'livemode' => false,
                    'metadata' => [],
                    'name' => 'Test Product',
                    'package_dimensions' => null,
                    'shippable' => null,
                    'statement_descriptor' => null,
                    'type' => 'service',
                    'unit_label' => null,
                    'updated' => 1553206699,
                    'url' => null,
                ],
            ],
        ];
    }

    private function stripeAccount()
    {
        return [
            'id' => 'acct_1Ej5Zt2eZvKYlo2C',
            'object' => 'customer',
            'account_balance' => 0,
            'address' => null,
            'balance' => 0,
            'created' => 1553206699,
            'currency' => 'usd',
            'default_source' => null,
            'delinquent' => false,
            'description' => null,
            'discount' => null,
            'email' => null,
            'invoice_prefix' => '010517a7',
            'invoice_settings' => [
                'custom_fields' => null,
                'default_payment_method' => null,
                'footer' => null,
            ],
            'livemode' => false,
            'metadata' => [],
            'name' => null,
            'next_invoice_sequence' => 1,
            'phone' => null,
            'preferred_locales' => [],
            'shipping' => null,
            'tax_exempt' => 'none',
        ];
    }

    private function stripePrices()
    {
        return [
            'object' => 'list',
            'data' => [
                [
                    'id' => 'price_1Ej5Zt2eZvKYlo2C',
                    'object' => 'price',
                    'active' => true,
                    'billing_scheme' => 'per_unit',
                    'created' => 1553206699,
                    'currency' => 'usd',
                    'livemode' => false,
                    'lookup_key' => null,
                    'metadata' => [],
                    'nickname' => null,
                    'product' => 'prod_E2qo3Zt2eZvKYlo2',
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
                ],
            ],
        ];
    }

    private function stripeSubscriptions()
    {
        return [
            'object' => 'list',
            'total_count' => 1,
            'has_more' => false,
            'data' => [
                [
                    'id' => 'sub_1Ej5Zt2eZvKYlo2C',
                    'object' => 'subscription',
                    'application_fee_percent' => null,
                    'billing' => 'charge_automatically',
                    'billing_cycle_anchor' => 1553206699,
                    'billing_thresholds' => null,
                    'cancel_at' => null,
                    'cancel_at_period_end' => false,
                    'canceled_at' => null,
                    'collection_method' => 'charge_automatically',
                    'created' => 1553206699,
                    'current_period_end' => 1553206699,
                    'current_period_start' => 1553206699,
                    'customer' => 'cus_E2qo3Zt2eZvKYlo2',
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
                                'id' => 'si_E2qo3Zt2eZvKYlo2',
                                'object' => 'subscription_item',
                                'billing_thresholds' => null,
                                'created' => 1553206699,
                                'metadata' => [],
                                'price' => 'price_1Ej5Zt2eZvKYlo2C',
                                'quantity' => 1,
                                'subscription' => 'sub_1Ej5Zt2eZvKYlo2C',
                                'tax_rates' => [],
                            ],
                        ],
                    ],
                    'latest_invoice' => 'in_1Ej5Zt2eZvKYlo2C',
                    'livemode' => false,
                    'metadata' => [],
                    'next_pending_invoice_item_invoice' => null,
                    'pending_invoice_item_interval' => null,
                    'pending_setup_intent' => null,
                    'pending_update' => null,
                    'plan' => [
                        'id' => 'price_1Ej5Zt2eZvKYlo2C',
                        'object' => 'plan',
                        'active' => true,
                        'aggregate_usage' => null,
                        'amount' => 1000,
                        'amount_decimal' => '1000',
                        'billing_scheme' => 'per_unit',
                        'created' => 1553206699,
                        'currency' => 'usd',
                        'interval' => 'month',
                        'interval_count' => 1,
                        'livemode' => false,
                        'metadata' => [],
                        'nickname' => null,
                        'product' => 'prod_E2qo3Zt2eZvKYlo2',
                        'tiers' => null,
                        'tiers_mode' => null,
                        'transform_usage' => null,
                        'trial_period_days' => null,
                        'usage_type' => 'licensed',
                    ],
                    'quantity' => 1,
                    'schedule' => null,
                    'start_date' => 1553206699,
                    'status' => 'active',
                    'tax_percent' => null,
                    'trial_end' => null,
                    'trial_start' => null,
                ],
            ],
        ];
    }

    private function stripeCustomers()
    {
        return [
            'object' => 'list',
            'total_count' => 1,
            'has_more' => false,
            'data' => [
                [
                    'id' => 'cus_E2qo3Zt2eZvKYlo2',
                    'object' => 'customer',
                    'address' => null,
                    'balance' => 0,
                    'created' => 1553206699,
                    'currency' => 'usd',
                    'default_source' => null,
                    'delinquent' => false,
                    'description' => null,
                    'discount' => null,
                    'email' => null,
                    'invoice_prefix' => '010517a7',
                    'invoice_settings' => [
                        'custom_fields' => null,
                        'default_payment_method' => null,
                        'footer' => null,
                    ],
                    'livemode' => false,
                    'metadata' => [],
                    'name' => null,
                    'next_invoice_sequence' => 1,
                    'phone' => null,
                    'preferred_locales' => [],
                    'shipping' => null,
                    'tax_exempt' => 'none',
                ],
            ],
        ];
    }

    private function stripeCoupon()
    {
        return [
            'id' => 'coupon_1Ej5Zt2eZvKYlo2C',
            'object' => 'coupon',
            'amount_off' => null,
            'created' => 1553206699,
            'currency' => null,
            'duration' => 'once',
            'duration_in_months' => null,
            'livemode' => false,
            'max_redemptions' => null,
            'metadata' => [],
            'name' => 'Test Coupon',
            'percent_off' => 10,
            'redeem_by' => 1553206699,
            'times_redeemed' => 0,
            'valid' => true,
        ];
    }
}
