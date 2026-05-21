<?php

namespace Tests\Feature;

use App\Http\Controllers\Client\CreateCheckoutService;
use App\Jobs\Schedule\SubscriptionsBillerJob;
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
use App\Models\Client;
use App\Models\Convert\Flow;
use App\Models\Intelligence\Scenario;
use App\Models\Pricing\Plan;
use App\Models\Twin;
use App\Models\Usage\AggregationValue;
use App\Models\Usage\Metric;
use App\Models\Usage\Summary;
use App\Models\Usage\UsageEvent;
use App\Services\Invoices\CustomerUsageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Helper\TestsAgainstStripe;
use Tests\TestCase;

class BonjoroFallbackFeatureTest extends TestCase
{
    use TestsAgainstStripe;

    public function test_inclusion()
    {
        DB::table('billing_subscriptions')->truncate();

        // ensure we're in the same timezone offset as the database
        $offset = DB::select("SELECT TIME_FORMAT(TIMEDIFF(NOW(), UTC_TIMESTAMP), '%H:%i') AS timezone_offset")[0]->timezone_offset;

        $user = $this->createUser();
        $org = $user->currentOrganization;
        $org->timezone = '+'.$offset;
        $org->save();

        /* @var Client $client */
        $client = $org->clients->first();

        $token = $client->createToken('api_token');

        $key = $token->accessToken->getApiKey();

        $this->assertNotNull($key);
        $this->assertStringStartsWith('client_live_', $key);

        $bsp = BillingProvider::factory()
            ->for($org)
            ->stripe()
            ->create();

        $product = Product::factory()
            ->for($org)
            ->create();

        $baseCharge = new Charge();
        $baseCharge->organization_id = $org->id;
        $baseCharge->mode = 'in_advance';
        $baseCharge->product_id = $product->id;
        $baseCharge->name = 'standard monthly charge';
        $baseCharge->type = 'standard';
        $baseCharge->currency = 'AUD';
        $baseCharge->amount = '1234'; // $12.34 AUD
        $baseCharge->save();

        $fallbackCharge = Charge::factory()
            ->for($org)
            ->create([
                'mode' => 'in_arrears',
                'product_id' => $product->id,
                'name' => 'fallback charge',
                'type' => 'standard',
                'currency' => 'AUD',
                'amount' => '5', // $0.05 AUD
                'amount_minimum_spend' => '500',
            ]);

        $plan = new Plan();
        $plan->organization_id  = $org->id;
        $plan->name             = 'monthly plan aud';
        $plan->renew_interval   = 'P1Y';
        $plan->billing_anchor   = 'anniversary';
        $plan->invoice_interval = 'P1M';
        $plan->currency         = 'AUD';
        $plan->save();

        $featureSet = FeatureSet::factory()
            ->for($org)
            ->create();

        [$platform, $templates, $videos, $fallbackVideos] = Feature::factory()
            ->for($featureSet)
            ->for($org)
            ->createMany([
                ['name' => 'platform_access'],
                ['name' => 'templates'],
                ['name' => 'videos'],
                ['name' => 'fallback_videos'],
            ]);
        ProductFeature::unguard();
        foreach ([$platform, $templates, $videos, $fallbackVideos] as $feature) {
            ProductFeature::query()->create([
                'product_id' => $product->id,
                'feature_id' => $feature->id,
            ]);
        }
        ProductFeature::reguard();

        // templates metric
        [$templateMetric, $videoMetric, $fallbackMetric] = Metric::factory()
            ->for($org)
            ->createMany([
                [
                    'event_name' => 'template_used',
                    'feature_id' => $templates->id,
                    'aggregation' => AggregationValue::Latest,
                    'field_name' => 'quantity',
                ],
                [
                    'event_name' => 'video_sent',
                    'feature_id' => $videos->id,
                    'aggregation' => AggregationValue::Sum,
                ],
                [
                    'event_name' => 'fallback_video_sent',
                    'feature_id' => $videos->id,
                    'aggregation' => AggregationValue::Count,
                ],
            ]);

        [$coreInclusion, $templatesInclusion, $videosInclusion, $fallbacksInclusion] = Inclusion::factory()
            ->for($plan)
            ->createMany([
                [
                    'charge_id' => $baseCharge->id,
                    'feature_id' => $platform->id,
                    'metric_id' => null,
                ],
                [
                    'charge_id' => null,
                    'feature_id' => $templates->id,
                    'metric_id' => $templateMetric->id,
                    'limit_unit' => 'template',
                    'default_limit' => 4,
                    // persistent
                    // warning
                ],
                [
                    'charge_id' => null,
                    'feature_id' => $videos->id,
                    'metric_id' => $videoMetric->id,
                    'limit_unit' => 'video',
                    'default_limit' => 50,
                    // reset every month
                    'reset_anchor' => 'invoice', // or could be billing_anchor (of plan)
                ],
                [
                    'charge_id' => $fallbackCharge->id,
                    'feature_id' => $fallbackVideos->id,
                    'metric_id' => $fallbackMetric->id,
                    'reset_anchor' => 'invoice',
                ],
            ]);

        $plan->refresh();

        // plan should have 4 features
        // plan should have 2 charges
        $this->assertSame(4, $plan->inclusions->count());
        $this->assertSame(2, $plan->charges->count());

        $customer = Customer::factory()
            ->for($org)
            ->for($bsp, 'billingProvider')
            ->create();
        $customerTwin = Twin::factory()
            ->for($org)
            ->create([
                'reference_id' => $customer->reference_id,
                'connector_id' => $bsp->id,
                'connector_type' => 'billing_provider',
                'type' => Customer::class,
                'linkable_type' => 'customer',
                'linkable_id' => $customer->id,
                'data' => [],
            ]);

        // base charge, invoice created as usual
        // fallback charge, when created, add new item to invoice
        $fallbacksCount = random_int(1, 25);
        for ($i = 0; $i < $fallbacksCount; $i++) {
            $res = $this->postJson('/v1/usage/events', [
                'customer' => $customer->getRouteKey(),
                'event' => 'fallback_video_sent',
                'quantity' => 1,
            ], [
                'Authorization' => 'Bearer '.$key,
            ]);
            $res->assertOk();;
        }

        // templates count, static
        $r = $this->postJson('/v1/usage/events', [
            'customer' => $customer->getRouteKey(),
            'event' => 'template_used',
            'properties' => [
                'quantity' => $templatesCount = random_int(2, 5),
            ],
        ])->assertOk();

        // videos count, reset every month
        $videosSentThisMonth = random_int(1, 25);
        for ($i = 0; $i < $videosSentThisMonth; $i++) {
            $this->postJson('/v1/usage/events', [
                'customer' => $customer->getRouteKey(),
                'event' => 'video_sent',
                'quantity' => 1,
            ])->assertOk();
        }

        // Template calculations
        $templateEvent = UsageEvent::query()
            ->where('event_name', 'template_used')
            ->where('customer_id', $customer->id)
            ->first();
        $templateEvent->generateSummary();

        $templateSummary = Summary::query()
            ->where('metric_id', $templateMetric->id)
            ->where('customer_id', $customer->id)
            ->first();
        $this->assertNotNull($templateSummary);
        $this->assertSame($templatesCount, $templateSummary->current_aggregation);

        $videoEvent = UsageEvent::query()
            ->where('event_name', 'video_sent')
            ->where('customer_id', $customer->id)
            ->first();
        $videoEvent->generateSummary();
        $videoSummary = Summary::query()
            ->where('metric_id', $videoMetric->id)
            ->where('customer_id', $customer->id)
            ->first();
        $this->assertNotNull($videoSummary);
        $this->assertSame($videosSentThisMonth, $videoSummary->current_aggregation);
        //

        $fallbackEvent = UsageEvent::query()
            ->where('event_name', 'fallback_video_sent')
            ->where('customer_id', $customer->id)
            ->first();
        $fallbackEvent->generateSummary();

        $fallbackSummary = Summary::query()
            ->where('metric_id', $fallbackMetric->id)
            ->where('customer_id', $customer->id)
            ->first();
        $this->assertNotNull($fallbackSummary);
        $this->assertSame($fallbacksCount, $fallbackSummary->current_aggregation);

        $usage = $customer->usageSummaries
            ->pluck('current_aggregation', 'event_name');

        $this->assertSame([
            'template_used' => $templatesCount,
            'video_sent' => $videosSentThisMonth,
            'fallback_video_sent' => $fallbacksCount,
        ], $usage->toArray());

        $schedule = Schedule::factory()
            ->for($org)
            ->for($customer)
            ->create();

        $baseDate = now();
        $subscription = Subscription::factory()
            ->for($customer)
            ->for($org)
            ->for($plan)
            ->create([
                'quantity' => 1,
                'schedule_id' => $schedule->id,
                'previous_subscription_id' => null,
                'current_state' => 'active',
                'plan_id' => $plan->id,
                'start_at' => $baseDate->copy()->subMonth(),
                'end_at' => $baseDate->copy()->addYear(),
                'cancel_at' => $baseDate->copy()->addYear(),
                'invoiced_at' => $baseDate->copy()->subMonth(),
                'renewed_at' => $baseDate->copy()->subMonth(),
            ]);
        $subTwin = Twin::factory()
            ->for($org)
            ->for($bsp, 'connector')
            ->create([
                'reference_id' => 'sub_'.Str::uuid(),
                'type' => Subscription::class,
                'linkable_type' => 'schedule',
                'linkable_id' => $subscription->schedule_id,
                'data' => [],
            ]);
        $subscription->twin_id = $subTwin->id;
        $subscription->save();

        $this->travelTo($baseDate->copy()->addMonth()->setHour(10));

        $this->assertNotNull($customer->subscriptions);
        $this->assertCount(1, $customer->subscriptions);

        $this->mockStripe([
            [json_encode(['id' => 123]), 200, []],
            [json_encode(['id' => 123]), 200, []],
        ]);

        SubscriptionsBillerJob::dispatch();

        $this->travelBack();

        $res = $this->postStripeWebhookEvent([
            'id' => 'evt_'.fake()->uuid,
            'type' => 'invoice.created',
            'account' => $bsp->lookup_key,
            'livemode' => $bsp->environment === 'live',
            'data' => [
                'object' => [
                    'object' => 'invoice',
                    'id' => 'inv_'.fake()->uuid,
                    'customer' => $customer->reference_id,
                    'amount_due' => 100,
                    'lines' => [
                        [
                            'id' => 'il_'.fake()->uuid,
                            'object' => 'line_item',
                            'type' => 'subscription',
                            'subscription' => $subTwin->reference_id,
                        ]
                    ],
                ]
            ],
        ]);
        $res->assertOk();

        $usageService = new CustomerUsageService(
            $customer,
            $subscription,
        );
        $b = $usageService->boundaries();
        $this->assertTrue(isset($b['charges_from_datetime']));
        $this->assertTrue(isset($b['charges_to_datetime']));

        $subscription->refresh();
        $this->assertNotNull($subscription->invoiced_at);
    }

    public function test_customer_usage()
    {
        $user = $this->createUser();
        $org = $user->currentOrganization;
//        $org->timezone = '+1000';
//        $org->save();

        /* @var Client $client */
        $client = $org->clients->first();

        $token = $client->createToken('api_token');

        $key = $token->accessToken->getApiKey();

        $this->assertNotNull($key);
        $this->assertStringStartsWith('client_live_', $key);

        $bsp = BillingProvider::factory()
            ->for($org)
            ->stripe()
            ->create();

        /* @var Client $client */
        $client = $org->clients->first();
        $token = $client->createToken('api_token');
        $key = $token->accessToken->getApiKey();

        // ----------------------------------
        $product = Product::factory()
            ->for($org)
            ->create();

        $baseCharge = new Charge();
        $baseCharge->organization_id = $org->id;
        $baseCharge->mode = 'in_advance';
        $baseCharge->product_id = $product->id;
        $baseCharge->name = 'standard monthly charge';
        $baseCharge->type = 'standard';
        $baseCharge->currency = 'AUD';
        $baseCharge->amount = '1234';
        $baseCharge->save();

        $fallbackCharge = Charge::factory()
            ->for($org)
            ->create([
                'mode' => 'in_arrears',
                'product_id' => $product->id,
                'name' => 'fallback charge',
                'type' => 'standard',
                'currency' => 'AUD',
                'amount' => '5', // $0.05 AUD
                'amount_minimum_spend' => '500',
            ]);

        $plan = new Plan();
        $plan->organization_id  = $org->id;
        $plan->name             = 'monthly plan aud';
        $plan->renew_interval   = 'P1Y';
        $plan->billing_anchor   = 'anniversary';
        $plan->invoice_interval = 'P1M';
        $plan->currency         = 'AUD';
        $plan->save();

        $featureSet = FeatureSet::factory()
            ->for($org)
            ->create();

        [$platform, $templates, $videos, $fallbackVideos] = Feature::factory()
            ->for($featureSet)
            ->for($org)
            ->createMany([
                ['name' => 'platform_access'],
                ['name' => 'templates'],
                ['name' => 'videos'],
                ['name' => 'fallback_videos'],
            ]);
        ProductFeature::unguard();
        foreach ([$platform, $templates, $videos, $fallbackVideos] as $feature) {
            ProductFeature::query()->create([
                'product_id' => $product->id,
                'feature_id' => $feature->id,
            ]);
        }
        ProductFeature::reguard();

        // templates metric
        [$templateMetric, $videoMetric, $fallbackMetric] = Metric::factory()
            ->for($org)
            ->createMany([
                [
                    'event_name' => 'template_used',
                    'feature_id' => $templates->id,
                    'aggregation' => AggregationValue::Latest,
                    'field_name' => 'quantity',
                ],
                [
                    'event_name' => 'video_sent',
                    'feature_id' => $videos->id,
                    'aggregation' => AggregationValue::Sum,
                ],
                [
                    'event_name' => 'fallback_video_sent',
                    'feature_id' => $videos->id,
                    'aggregation' => AggregationValue::Count,
                ],
            ]);

        [$coreInclusion, $templatesInclusion, $videosInclusion, $fallbacksInclusion] = Inclusion::factory()
            ->for($plan)
            ->createMany([
                [
                    'charge_id' => $baseCharge->id,
                    'feature_id' => $platform->id,
                    'metric_id' => null,
                ],
                [
                    'charge_id' => null,
                    'feature_id' => $templates->id,
                    'metric_id' => $templateMetric->id,
                    'limit_unit' => 'template',
                    'default_limit' => 4,
                    // persistent
                    // warning
                ],
                [
                    'charge_id' => null,
                    'feature_id' => $videos->id,
                    'metric_id' => $videoMetric->id,
                    'limit_unit' => 'video',
                    'default_limit' => 50,
                    // reset every month
                    'reset_anchor' => 'invoice', // or could be billing_anchor (of plan)
                ],
                [
                    'charge_id' => $fallbackCharge->id,
                    'feature_id' => $fallbackVideos->id,
                    'metric_id' => $fallbackMetric->id,
                    'reset_anchor' => 'invoice',
                ],
            ]);
        // ----------------------------------
        $customer = Customer::factory()
            ->for($org)
            ->for($bsp, 'billingProvider')
            ->create();

        $schedule = Schedule::factory()
            ->for($org)
            ->for($customer)
            ->create();

        $baseDate = now();
        $subscription = Subscription::factory()
            ->for($customer)
            ->for($org)
            ->for($plan)
            ->create([
                'quantity' => 1,
                'schedule_id' => $schedule->id,
                'previous_subscription_id' => null,
                'current_state' => 'active',
                'plan_id' => $plan->id,
                'start_at' => $baseDate->copy()->subMonth(),
                'end_at' => $baseDate->copy()->addYear(),
                'cancel_at' => $baseDate->copy()->addYear(),
                'invoiced_at' => $baseDate->copy()->subMonth(),
                'renewed_at' => $baseDate->copy()->subMonth(),
            ]);

        $count = random_int(50, 220);
        for ($i = 0; $i < $count; $i++) {
            $randomQuantity = random_int(1, 100);
            $res = $this->postJson('/v1/usage/events', [
                'customer' => $customer->getRouteKey(),
                'event' => 'template_used',
                'properties' => [
                    'quantity' => $randomQuantity,
                ],
            ], [
                'Authorization' => 'Bearer '.$key,
            ]);
            $res->assertOk();
        }

        $usageRes = $this->getJson('/v1/customers/'.$customer->getRouteKey().'/usage', [
            'Authorization' => 'Bearer '.$key,
        ]);
        $usageRes->assertOk();
        $data = $usageRes->json();

        // Assert the structure of the response
        $this->assertArrayHasKey('period_start_at', $data);
        $this->assertArrayHasKey('period_end_at', $data);
        $this->assertArrayHasKey('amount', $data);
        $this->assertArrayHasKey('currency', $data);
        $this->assertArrayHasKey('fees', $data);

        // Assert date fields are in timestamp format
        $this->assertIsInt(strtotime($data['period_start_at']));
        $this->assertIsInt(strtotime($data['period_end_at']));

        $total = ($count <= 100) ? 500 : ($count * 5);
        // Check amount and currency are accurate
        $this->assertEquals($total, $data['amount']);
        $this->assertEquals('USD', $data['currency']);

        // Validate fees structure
        $fees = $data['fees'];
        $this->assertNotEmpty($fees);
        foreach ($fees as $fee) {
            $this->assertArrayHasKey('amount', $fee);
            $this->assertArrayHasKey('description', $fee);
            $this->assertArrayHasKey('charge', $fee);
            $this->assertArrayHasKey('metric', $fee);

            $this->assertEquals($count, $fee['usage']);
            $this->assertEquals($total, $fee['amount']);

            // Validate charge details
            $charge = $fee['charge'];
            $this->assertEquals('fallback charge', $charge['name']);
            $this->assertEquals('AUD', $charge['currency']);
            $this->assertEquals('standard', $charge['type']);
            $this->assertEquals('500', $charge['amount_minimum_spend']);
            $this->assertEquals('5', $charge['amount']);
            $this->assertEquals('in_arrears', $charge['mode']);

            // Validate metric details
            $metric = $fee['metric'];
            $this->assertEquals('fallback_video_sent', $metric['event_name']);
            $this->assertEquals('COUNT', $metric['aggregation']);
            $this->assertEquals('persistent', $metric['type']);
        }
    }

    public function test_usage_estimation()
    {
        $user = $this->createUser();
        $org = $user->currentOrganization;

        $customer = Customer::factory()
            ->for($org)
            ->create([
                'email' => 'albert+'.mt_rand(1111111111, 11111111111).'@bonjoro.com',
                'reference_id' => 'cus_'.Str::random(15),
            ]);

        $plan = Plan::factory()
            ->create([
                'lookup_key' => 'usd-2023-vm-pro-yearly-1',
                'renew_interval' => 'P1Y',
                'invoice_interval' => 'P1Y',
                'status' => 'active',
            ]);
        $feature = Feature::factory()
            ->for($org)
            ->create();
        $metric = Metric::factory()
            ->for($org)
            ->for($feature)
            ->create();
        $charge = Charge::factory()
            ->for($org)
            ->create([
                'name' => 'Fallback Charge',
                'minimum_billable_usage' => 1,
                'amount_minimum_spend' => 500,
                'amount' => 5,
                'currency' => 'USD',
            ]);

        $inclusion = Inclusion::factory()
            ->for($charge)
            ->for($plan)
            ->for($metric)
            ->for($feature)
            ->create([
                'reset_anchor' => 'invoice',
            ]);

        $schedule = Schedule::factory()
            ->for($org)
            ->for($customer)
            ->create();

        $subscription = Subscription::factory()
            ->for($customer)
            ->for($plan)
            ->for($org)
            ->for($schedule)
            ->create([
                'quantity' => 1,
                'current_state' => 'active',
                'start_at' => now()->subWeek()->subDay(),
            ]);

        $usageService = new CustomerUsageService(
            $customer,
            $subscription,
        );
        $boundaries = $usageService->boundaries();

        // create 3 usage events
        DB::table('usage_events')
            ->insert([
                'organization_id' => $org->id,
                'client_id' => $org->clients->first()->id,
                'unique_id' => Str::random(15),
                'customer_id' => $customer->id,
                'event_name' => 'fallback',
                'created_at' => now()->subWeek()->hour(mt_rand(1, 23)),
            ]);

        $this->actingAs($user);
        $e = $this->getJson('/v1/customers/'.$customer->getRouteKey().'/usage', [
        ]);
        $e->assertSuccessful();
    }

    public function test_checkout_values()
    {
        $user = $this->createUser();
        $org = $user->currentOrganization;

        $customer = Customer::factory()
            ->for($org)
            ->create([
                'email' => 'aaron+'.mt_rand(1111111111, 11111111111).'@bonjoro.com',
                'reference_id' => 'cus_'.Str::random(15),
            ]);

        $bsp = BillingProvider::factory()
            ->for($org)
            ->stripe()
            ->create();
        $client = $org->liveClient();
        $client->billingProvider()->associate($bsp);
        $client->save();

        $flow = Flow::factory()
            ->for($org)
            ->create();
        /* @var Scenario $scenario */
        $scenario = Scenario::factory()
            ->for($flow)
            ->create();

        $plan = Plan::factory()
            ->create([
                'lookup_key' => 'usd-2023-vm-pro-yearly-1',
                'renew_interval' => 'P1Y',
                'status' => 'active',
            ]);

        $bundleItem = $scenario->bundleItems()->create([
            'purchasable_id' => $plan->id,
            'purchasable_type' => 'plan',
        ]);

        $createPurchase = new CreateCheckoutService();

        $purchase = $createPurchase(
            $org->liveClient(),
            $scenario,
            null,
            null,
        );

        $this->assertSame($plan->currency->getCode(), $purchase->currency->getCode());
        $this->assertSame($plan->renew_interval->spec(), $purchase->renew_interval->spec());
    }
}
