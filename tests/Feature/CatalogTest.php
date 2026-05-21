<?php

namespace Tests\Feature;

use App\Models\Account\Agent;
use App\Models\Account\Customer;
use App\Models\Billing\BillingProvider;
use App\Models\Billing\Charge;
use App\Models\Billing\Schedule;
use App\Models\Billing\Subscription;
use App\Models\Catalog\Feature;
use App\Models\Catalog\FeatureSet;
use App\Models\Catalog\Inclusion;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductFeature;
use App\Models\Convert\CheckoutState;
use App\Models\Convert\Flow;
use App\Models\Intelligence\Scenario;
use App\Models\Management\Organization;
use App\Models\Pricing\Plan;
use App\Models\Store\Purchase;
use App\Models\Twin;
use App\Models\Usage\Metric;
use App\Models\User;
use App\Models\Values\PlanType;
use Firebase\JWT\JWT;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Money\Currency;
use Money\Money;
use Stripe\Price;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_product_lineage(): void
    {
        $org = Organization::factory()->create();

        $freeProduct = Product::factory()
            ->for($org)
            ->free()
            ->create();

        $paidProduct = Product::factory()
            ->for($org)
            ->premium()
            ->create();

        $videoMessagingFeatureSet = FeatureSet::factory()
            ->for($org)
            ->create();

        $videoMessagesFeature = Feature::factory()
            ->for($org)
            ->for($videoMessagingFeatureSet)
            ->create([
                'lookup_key' => 'videoMessageLimit',
                'name' => 'Video Message Limit',
            ]);

        // recurring or metered
        Metric::unguard();
        $metric = $videoMessagesFeature
            ->metrics()
            ->create([
                'organization_id' => $org->id,
                'event_name' => 'video_message',
                'aggregation' => 'LATEST',
                'field_name' => 'video_count',
                'type' => 'persistent',
            ]);

        ProductFeature::unguard();

        // add limit of 50 total messages to free product here
        $vmFreeProductFeature = $freeProduct
            ->productFeatures()
            ->create([
                'feature_id' => $videoMessagesFeature->id,
                'allowance' => 50,
                // meter for this is persistent
                'reset_period' => 'persistent',
                'unit' => 'video_message',
            ]);

        $vmPaidProductFeature = $paidProduct
            ->productFeatures()
            ->create([
                'feature_id' => $videoMessagesFeature->id,
                'allowance' => 50,
                'reset_period' => 'anniversary',
                'unit' => 'video_message',
            ]);

        $zeroCharge = Charge::factory()
            ->for($freeProduct)
            ->for($org)
            ->create([
                'name' => 'Free Product Charge',
                'currency' => 'USD',
                'product_id' => $freeProduct->id,
                'amount' => new Money(0, new Currency('USD')),
                'mode' => 'in_advance',
            ]);
        $this->assertTrue($zeroCharge->isFree());

        $freePlan = Plan::factory()
            ->for($org)
            ->create([
                'type' => PlanType::provisional,
                'currency' => 'USD',
            ]);

        $freePlanVmInclusion = $freePlan
            ->inclusions()
            ->create([
                'feature_id' => $videoMessagesFeature->id,
                'default_limit' => 50,
                'limit_unit' => 'greet',
                'reset_anchor' => 'invoice_interval',
                'is_approved' => false,
            ]);

        $this->assertSame($metric->feature_id, $videoMessagesFeature->id);

        /* @var Plan $paidPlan */
        $paidPlan = Plan::factory()
            ->for($org)
            ->standard()
            ->create([
                'type' => PlanType::standard,
                'currency' => 'USD',
                'renew_interval' => 'P1Y',
            ]);
        $paidChargePerSeat = Charge::factory()
            ->for($paidProduct)
            ->for($org)
            ->create([
                'mode' => 'in_advance',
                'amount' => new Money(fake()->randomNumber(4), new Currency('USD')),
            ]);

        $inclusion = Inclusion::factory()
            ->for($paidPlan)
            ->for($paidProduct)
            ->for($paidChargePerSeat, 'charge')
            ->for($metric)
            ->create();

        $this->assertNotNull($inclusion->metric);
        $this->assertNotNull($inclusion->metric->feature_id, $videoMessagesFeature->id);
        $customerId = fake()->uuid();

        $customer = Customer::factory()
            ->for($org)
            ->create([
                'reference_id' => $customerId,
            ]);

        $schedule = Schedule::factory()
            ->for($customer)
            ->for($org)
            ->create();

        Subscription::factory()
            ->for($schedule)
            ->for($org)
            ->for($paidPlan)
            ->create();

        $planInclusion = $paidPlan
            ->inclusions()
            ->where('charge_id', $paidChargePerSeat->id)
            ->first();

        $summary = $planInclusion?->metric->customerSummary($customer);
        $this->assertNull($summary);
        $userId = Str::uuid()->toString();
        $groupId = Str::random(6).'-'.Str::slug(fake()->company);

       $client = $org
           ->clients()
           ->first();
        $billing = BillingProvider::factory()
            ->for($org)
            ->stripe()
            ->create();
        $client->billingProvider()->associate($billing)->save();

        $videoCount = fake()->numberBetween(1, 100);
        $encodedJwt = JWT::encode([
            'sub' => $userId,
            'groups' => [$groupId],
            'customer' => $customerId,
            'usage' => [
                $metric->event_name => $videoCount,
            ],
        ], $client->getSecretStr(), 'HS256', $client->getRouteKey());

        $usageResponse = $this->post('/client/usage/events', [
            'customer_id' => $customerId,
            'client_id' => $client->getRouteKey(),
            'event_name' => $metric->event_name,
            'properties' => [
                $metric->field_name => $videoCount,
            ],
        ], [
            'Authorization' => 'Bearer '.$encodedJwt,
        ]);
        $usageResponse->assertCreated();
        $this->assertTrue($videoCount > 0);

        $summary = $planInclusion->metric->customerSummary($customer);

        $this->assertNotNull($summary);

        $this->assertSame($videoCount, $summary->current_aggregation);

        // create a checkout!?
        $workflow = Flow::factory()
            ->for($org)
            ->create();


        $paywall = Scenario::factory()
            ->for($workflow)
            ->for($org)
            ->create([
                'scheme_id' => 1,
            ]);
        $paywall->bundleItems()->create([
            'purchasable_id' => $paidPlan->id,
            'purchasable_type' => 'plan',
        ]);

        // todo: associate agent here!
        $agent = Agent::factory()
            ->for($org)
            ->create([
                'lookup_key' => $userId,
            ]);
        $agent->associateWithCustomer($customer);

        $sessionRes = $this->getJson('/client/session', [
            'Authorization' => 'Bearer '.$encodedJwt,
        ]);
        $sessionRes->assertSuccessful();
        $paywallRes = $this->getJson('/client/paywalls/'.$paywall->getRouteKey().'?collector='.$sessionRes->json('collector'), [
            'Authorization' => 'Bearer '.$encodedJwt,
        ]);
        $paywallRes->assertSuccessful();
        $checkoutResponse = $this->getJson('/client/checkouts/'.data_get($paywallRes, 'checkout.id'), [
            'Authorization' => 'Bearer '.$encodedJwt,
            'Plandalf-Cart' => $paywallRes->json('cart_token'),
        ]);
        $checkoutResponse->assertSuccessful();
        $data = $checkoutResponse->json();

        $checkout = Purchase::retrieve($data['id']);
        $this->assertNotNull($checkout);

        $this->assertSame(CheckoutState::CREATED->value, $data['current_state']);
        $this->assertSame(CheckoutState::CREATED, $checkout->current_state);
        $this->assertNotNull($data['summary']);

        // todo: amount validations!
        $this->assertSame('customer', $data['customer']['object']);
        $this->assertSame(
            $paidPlan->name,
            Arr::get($data, 'summary')
        );
        $this->assertSame($paidPlan->currency->getCode(), $data['currency_code']);

        $total = $paidPlan->charges
            ->first()->amount->multiply(intval($summary->current_aggregation));

        $this->assertSame($total->getAmount(), strval($data['amount_total']));

        $newVersion = Product::factory()
            ->for($org)
            ->for($paidProduct, 'ancestor')
            ->create();

        $productFeature = new ProductFeature;
        $productFeature->product()->associate($newVersion);
        $productFeature->feature()->associate($videoMessagesFeature);
        $productFeature->save();

        $this->assertCount(1, $newVersion->features);
        $currency = fake()->currencyCode;

        $baseCharge = Charge::factory()
            ->for($newVersion)
            ->for($org)
            ->create([
                'name' => $newVersion->name.' charge',
                'currency' => $currency,
            ]);

        $plan = Plan::factory()
            ->for($org)
            ->create([
                'currency' => $currency,
            ]);
        Inclusion::factory()
            ->for($plan)
            ->for($newVersion)
            ->for($baseCharge, 'charge')
            ->create();
    }

    public function test_import_standard_charge_accurately()
    {
        $org = $this->createOrg('test_import_charges_accurately-org');
        $billing = BillingProvider::factory()
            ->for($org)
            ->stripe()
            ->create();
        $user = User::factory()->create();
        $org->users()->save($user, ['role' => 'owner']);

        $price = Price::constructFrom(json_decode('{"id":"price_1Msur2FmvUKqVS2HSsf2D8tu","object":"price","active":true,"billing_scheme":"per_unit","created":1680557036,"currency":"aud","custom_unit_amount":null,"livemode":false,"lookup_key":null,"metadata":[],"nickname":"Can I borrow a tenner?","product":"prod_NeDHvS07cyhD9n","recurring":{"aggregate_usage":null,"interval":"month","interval_count":1,"meter":null,"trial_period_days":null,"usage_type":"licensed"},"tax_behavior":"inclusive","tiers_mode":null,"transform_quantity":null,"type":"recurring","unit_amount":1234,"unit_amount_decimal":"1234"}', true));

        Twin::unguard();
        $twin = Twin::query()
            ->updateOrCreate([
                'reference_id' => $price->id,
                'connector_id' => $billing->id,
                'connector_type' => 'billing_provider',
                'organization_id' => $billing->organization_id,
            ], Twin::fromStripeObject($price)->toArray());
        Twin::reguard();
        $product = Product::factory()
            ->for($org)
            ->create();

        $charge = new Charge;
        $charge->organization_id = $org->id;
        $charge->product_id = $product->id;
        $charge->fillFromTwin($twin);
        $charge->save();

        $this->assertSame('AUD', $charge->currency->getCode());
        $this->assertSame('price_1Msur2FmvUKqVS2HSsf2D8tu', $charge->name);
        $this->assertSame('standard', $charge->type);
        $this->assertSame('in_advance', $charge->mode);
        $this->assertSame('1234', $charge->amount->getAmount());

        $this->actingAs($user);
        $res = $this->getJson('/v1/billing/charges/'.$charge->getRouteKey());
        $res->assertSuccessful();
    }

    public function test_import_graduated_charges_accurately()
    {
        $org = $this->createOrg('test_import_charges_accurately-org');
        $billing = BillingProvider::factory()
            ->for($org)
            ->stripe()
            ->create();

        $tieredPriceJson = '{"id":"price_1PK4FFFmvUKqVS2HKhgVdBu7","object":"price","active":true,"billing_scheme":"tiered","created":1716580661,"currency":"aud","custom_unit_amount":null,"livemode":false,"lookup_key":null,"metadata":[],"nickname":null,"product":"prod_PpPYPHPoR9Uo5N","recurring":{"aggregate_usage":null,"interval":"month","interval_count":1,"meter":null,"trial_period_days":null,"usage_type":"licensed"},"tax_behavior":"inclusive","tiers":[{"flat_amount":401,"flat_amount_decimal":"401","unit_amount":400,"unit_amount_decimal":"400","up_to":5},{"flat_amount":301,"flat_amount_decimal":"301","unit_amount":300,"unit_amount_decimal":"300","up_to":7},{"flat_amount":201,"flat_amount_decimal":"201","unit_amount":200,"unit_amount_decimal":"200","up_to":9},{"flat_amount":101,"flat_amount_decimal":"101","unit_amount":100,"unit_amount_decimal":"100","up_to":11},{"flat_amount":51,"flat_amount_decimal":"51","unit_amount":50,"unit_amount_decimal":"50","up_to":null}],"tiers_mode":"graduated","transform_quantity":null,"type":"recurring","unit_amount":null,"unit_amount_decimal":null}';

        $price = Price::constructFrom(json_decode($tieredPriceJson, true));

        Twin::unguard();
        $twin = Twin::query()
            ->updateOrCreate([
                'reference_id' => $price->id,
                'connector_id' => $billing->id,
                'connector_type' => 'billing_provider',
                'organization_id' => $billing->organization_id,
            ], Twin::fromStripeObject($price)->toArray());
        Twin::reguard();

        $charge = new Charge;
        $charge->fillFromTwin($twin);

        $this->assertSame('AUD', $charge->currency->getCode());
        $this->assertSame('price_1PK4FFFmvUKqVS2HKhgVdBu7', $charge->name);
        $this->assertSame('graduated', $charge->type);
        $this->assertSame('in_advance', $charge->mode);
        $this->assertSame([
            [
                'flat_amount' => '401',
                'unit_amount' => '400',
                'up_to' => 5,
            ],
            [
                'flat_amount' => '301',
                'unit_amount' => '300',
                'up_to' => 7,
            ],
            [
                'flat_amount' => '201',
                'unit_amount' => '200',
                'up_to' => 9,
            ],
            [
                'flat_amount' => '101',
                'unit_amount' => '100',
                'up_to' => 11,
            ],
            [
                'flat_amount' => '51',
                'unit_amount' => '50',
                'up_to' => null,
            ],
        ], $charge->properties);
    }
}
