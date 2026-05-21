<?php

namespace Tests\Feature;

use App\Billing\Charges\GraduatedCharge;
use App\Billing\Charges\PackageCharge;
use App\Billing\Charges\StandardCharge;
use App\Billing\Charges\VolumeCharge;
use App\Billing\RenewalCalculator;
use App\Models\Account\Customer;
use App\Models\Catalog\Feature;
use App\Models\Catalog\Inclusion;
use App\Models\Pricing\Plan;
use App\Models\Usage\AggregationValue;
use App\Models\Usage\Metric;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Money\Currency;
use Money\Money;
use Stripe\Price as StripePrice;
use Stripe\Product as StripeProduct;
use Tests\TestCase;

class PricingTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_single_standard_charge(): void
    {
        // should be w/ stripe also
        $org = $this->createOrg();

        $stripeProduct = StripeProduct::constructFrom([
            'id' => 'prod_'.Str::random(24),
            'name' => 'My SaaS Product',
            'type' => 'service',
            'unit_label' => 'user',
            'active' => true,
        ]);
        $stripeMonthlyPrice = StripePrice::constructFrom([
            'id' => 'price_'.Str::random(24),
            'product' => $stripeProduct->id,
            'unit_amount' => 1000,
            'currency' => 'usd',
            'recurring' => [
                'interval' => 'month',
            ],
            // if recurring is set, its probably being used as a plan!
        ]);

        $plan = Plan::factory()->for($org)->create(['currency' => 'AUD']);

        $baseCharge = StandardCharge::factory()
            ->for($org)
            ->standard()
            ->create(['currency' => 'AUD']);

        $coreInclusion = Inclusion::factory()->create([
           'plan_id' => $plan->id,
           'feature_id' => null,
           'product_id' => null,
           'charge_id' => $baseCharge->id,
           'name' => 'Core Charge',
        ]);

        $plan->fresh();

        $customer = Customer::factory()
            ->for($org)
            ->create();

        $calculator = new RenewalCalculator(purchasable: $plan, customer: $customer);

        $sum = $calculator->calculate();
        $this->assertTrue(
            (new Money(1000, $plan->currency))->equals($sum)
        );
    }

    public function test_graduated_charge(): void
    {
        $org = $this->createOrg();

        $plan = Plan::factory()
            ->for($org)
            ->create(['currency' => 'AUD']);

        $baseCharge = GraduatedCharge::factory()
            ->for($org)
            ->graduated()
            ->create([
                'currency' => 'AUD',
                'properties' => [
                    ['up_to' => 10, 'unit_amount' => 1000, 'flat_amount' => 1000],
                    ['up_to' => 20, 'unit_amount' => 900, 'flat_amount' => null],
                    ['up_to' => null, 'unit_amount' => 800, 'flat_amount' => null],
                ],
            ]);
        $baseCharge->refresh();

        $seatMetric = Metric::factory()
            ->for($org)
            ->create([
                'feature_id' => 1,
                'event_name' => 'seats_reported',
                'aggregation' => AggregationValue::Latest,
                'type' => 'persistent',
                'field_name' => 'quantity',
            ]);

        $coreInclusion = Inclusion::factory()->create([
            'plan_id' => $plan->id,
            'feature_id' => null,
            'product_id' => null,
            'metric_id' => $seatMetric->id,
            'charge_id' => $baseCharge->id,
            'name' => 'Core Charge',
        ]);

        $plan->fresh();


        $customer = Customer::factory()
            ->for($org)
            ->create();

        $calculator = new RenewalCalculator(purchasable: $plan, customer: $customer);

        $seats = 25;
        DB::table('usage_events')
            ->insert([
                'client_id' => 1,
                'unique_id' => Str::random(24),
                'organization_id' => $org->id,
                'customer_id' => $customer->id,
                'event_name' => 'seats_reported',
                'properties' => json_encode([
                    'quantity' => $seats,
                ]),
            ]);

        $sum = $calculator->calculate();

        $this->assertTrue(
            (new Money(24000, new Currency('AUD')))->equals($sum)
        );
    }

    public function test_volume_charge(): void
    {
        $org = $this->createOrg();

        $plan = Plan::factory()
            ->for($org)
            ->create(['currency' => 'AUD']);

        $baseCharge = VolumeCharge::factory()
            ->for($org)
            ->volume()
            ->create([
                'currency' => 'AUD',
                'properties' => [
                    ['up_to' => 10, 'unit_amount' => 1000, 'flat_amount' => null],
                    ['up_to' => 20, 'unit_amount' => 900, 'flat_amount' => null],
                    ['up_to' => null, 'unit_amount' => 800, 'flat_amount' => null],
                ],
            ]);
        $baseCharge->refresh();

        // todo: use metrics!
        $seatMetric = Metric::factory()
            ->for($org)
            ->create([
                'feature_id' => 1,
                'event_name' => 'seats_reported',
                'aggregation' => AggregationValue::Latest,
                'type' => 'persistent',
                'field_name' => 'quantity',
            ]);

        $coreInclusion = Inclusion::factory()->create([
            'plan_id' => $plan->id,
            'feature_id' => null,
            'product_id' => null,
            'metric_id' => $seatMetric->id,
            'charge_id' => $baseCharge->id,
            'name' => 'Core Charge',
        ]);

        $plan->fresh();


        $customer = Customer::factory()
            ->for($org)
            ->create();

        $calculator = new RenewalCalculator(purchasable: $plan, customer: $customer);

        $seats = 25;
        DB::table('usage_events')
            ->insert([
                'client_id' => 1,
                'unique_id' => Str::random(24),
                'organization_id' => $org->id,
                'customer_id' => $customer->id,
                'event_name' => 'seats_reported',
                'properties' => json_encode([
                    'quantity' => $seats,
                ]),
            ]);

        $sum = $calculator->calculate();

        $this->assertTrue(
            (new Money(20000, new Currency('AUD')))->equals($sum)
        );
    }

    public function test_package_charge(): void
    {
        $org = $this->createOrg();

        $plan = Plan::factory()
            ->for($org)
            ->create(['currency' => 'AUD']);

        $baseCharge = PackageCharge::factory()
            ->for($org)
            ->package()
            ->create([
                'currency' => 'AUD',
                'properties' => [
                    'package_size' => 100,
                    'free_units' => 100,
                    'amount' => 500,
                ],
            ]);
        $baseCharge->refresh();

        // todo: use metrics!
        $seatMetric = Metric::factory()
            ->for($org)
            ->create([
                'feature_id' => 1,
                'event_name' => 'seats_reported',
                'aggregation' => AggregationValue::Latest,
                'type' => 'persistent',
                'field_name' => 'quantity',
            ]);

        $coreInclusion = Inclusion::factory()->create([
            'plan_id' => $plan->id,
            'feature_id' => null,
            'product_id' => null,
            'metric_id' => $seatMetric->id,
            'charge_id' => $baseCharge->id,
            'name' => 'Core Charge',
        ]);

        $plan->fresh();

        $customer = Customer::factory()
            ->for($org)
            ->create();

        $calculator = new RenewalCalculator(purchasable: $plan, customer: $customer);

        $seats = 350;
        DB::table('usage_events')
            ->insert([
                'client_id' => 1,
                'unique_id' => Str::random(24),
                'organization_id' => $org->id,
                'customer_id' => $customer->id,
                'event_name' => 'seats_reported',
                'properties' => json_encode([
                    'quantity' => $seats,
                ]),
            ]);

        $sum = $calculator->calculate();

        $this->assertTrue(
            (new Money(1500, new Currency('AUD')))->equals($sum)
        );
    }

    public function test_only_estimate_core_charges()
    {
        // should be w/ stripe also
        $org = $this->createOrg();

        $stripeProduct = StripeProduct::constructFrom([
            'id' => 'prod_'.Str::random(24),
            'name' => 'My SaaS Product',
            'type' => 'service',
            'unit_label' => 'user',
            'active' => true,
        ]);
        $stripeMonthlyPrice = StripePrice::constructFrom([
            'id' => 'price_'.Str::random(24),
            'product' => $stripeProduct->id,
            'unit_amount' => 1000,
            'currency' => 'usd',
            'recurring' => [
                'interval' => 'month',
            ],
            // if recurring is set, its probably being used as a plan!
        ]);

        $plan = Plan::factory()->for($org)->create(['currency' => 'AUD']);

        $baseCharge = StandardCharge::factory()
            ->for($org)
            ->standard()
            ->create(['currency' => 'AUD']);

        $coreInclusion = Inclusion::factory()->create([
            'plan_id' => $plan->id,
            'feature_id' => null,
            'product_id' => null,
            'charge_id' => $baseCharge->id,
            'metric_id' => null,
            'name' => 'Core Charge',
        ]);

        $otherCharge = PackageCharge::factory()
            ->for($org)
            ->package()
            ->create([
                'currency' => 'AUD',
                'mode' => 'in_arrears',
                'properties' => [
                    'package_size' => 100,
                    'free_units' => 100,
                    'amount' => 500,
                ],
            ]);

        $widgetsFeature = Feature::factory()
            ->for($org)
            ->create([
                'name' => 'Widgets',
            ]);

        $widgetMetric = Metric::factory()
            ->for($org)
            ->create([
                'feature_id' => $widgetsFeature->id,
                'event_name' => 'widgets_used',
                'aggregation' => AggregationValue::Latest,
                'type' => 'transient',
                'field_name' => 'quantity',
            ]);

        Inclusion::factory()->create([
            'plan_id' => $plan->id,
            'feature_id' => $widgetsFeature->id,
            'product_id' => null, //
            'charge_id' => $otherCharge->id,
            'metric_id' => $widgetMetric->id,
            'name' => 'Other Charge',
        ]);

        $plan->fresh();

        $customer = Customer::factory()
            ->for($org)
            ->create();

        $seats = 350;
        DB::table('usage_events')
            ->insert([
                'client_id' => 1,
                'unique_id' => Str::random(24),
                'organization_id' => $org->id,
                'customer_id' => $customer->id,
                'event_name' => 'widgets_used',
                'properties' => json_encode([
                    'quantity' => $seats,
                ]),
            ]);

        $calculator = new RenewalCalculator(purchasable: $plan, customer: $customer);

        $sum = $calculator->calculate();
        $this->assertTrue(
            (new Money(1000, $plan->currency))->equals($sum)
        );
    }
}
