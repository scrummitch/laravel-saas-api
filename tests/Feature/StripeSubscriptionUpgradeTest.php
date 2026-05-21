<?php

namespace Tests\Feature;

use App\Jobs\ImportStripeSubscriptionStatuses;
use App\Models\Account\Customer;
use App\Models\Billing\BillingProvider;
use App\Models\Billing\Charge;
use App\Models\Billing\Schedule;
use App\Models\Management\Organization;
use App\Models\Pricing\Plan;
use App\Models\Twin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Customer as StripeCustomer;
use Stripe\Price;
use Stripe\Subscription as StripeSubscription;
use Tests\Helper\TestsAgainstStripe;
use Tests\TestCase;

class StripeSubscriptionUpgradeTest extends TestCase
{
    use TestsAgainstStripe;

    public function test_update_status_job(): void
    {
        DB::table('billing_subscriptions')->truncate();
        DB::table('twins')->truncate();

        $org = $this->createOrg();
        $plan = Plan::factory()
            ->for($org)
            ->create();
        $bsp = BillingProvider::factory()
            ->for($org)
            ->stripe()
            ->create();

        $charge = Charge::factory()
            ->for($org)
            ->create(['product_id' => 1,]);

        $ins = [
            'plan_id' => $plan->id,
            'charge_id' => $charge->id,
            'ulid' => Str::ulid()->toString(),
        ];
        DB::table('catalog_inclusions')->insert($ins);

        $price = Twin::factory()
            ->for($org)
            ->create([
                'reference_id' => 'price_'.Str::random(),
                'connector_id' => $bsp->id,
                'connector_type' => 'billing_provider',
                'type' => Price::class,
                'data' => [],
                'linkable_type' => 'charge',
                'linkable_id' => $charge->id,
            ]);

        $invoiceResponses = [];
        for ($i = 0; $i < 10; $i++) {
            $customer = $this->createStripeCustomer($org, $bsp);
            $sub = $this->createStripeSubscription($customer, $price->reference_id);

            $twin = Twin::factory()
                ->for($org)
                ->create([
                    'reference_id' => $sub->id,
                    'connector_id' => $bsp->id,
                    'connector_type' => 'billing_provider',
                    'type' => StripeSubscription::class,
                    'data' => $sub->toArray(),
                    'updated_at' => now(),
                ]);

            $schedule = Schedule::factory()
                ->for($customer)
                ->for($org)
                ->create();

            $id = DB::table('billing_subscriptions')
                ->insertGetId([
                    'organization_id' => $org->id,
                    'schedule_id' => $schedule->id,
                    'plan_id' => $plan->id,
                    'twin_id' => $twin->id,
                    'quantity' => 1,
                    'created_at' => now(),
                ]);
            // this is schedules now!
            $twin->linkable_id = $id;
            $twin->save();

            $invoiceResponses[] = [json_encode($this->invoiceResponse($twin->reference_id)), 200, []];
        }

        $this->mockStripe([
           ...$invoiceResponses,
        ]);

        ImportStripeSubscriptionStatuses::dispatch();
    }

    protected function invoiceResponse(string $subscriptionId)
    {
        return [
            'object' => 'list',
            'url' => '/v1/invoices',
            'has_more' => false,
            'data' => [
               [
                   'id' => 'in_'.Str::random(22),
                   'object' => 'invoice',
                   'customer' => 'cus_'.Str::random(22),
                   'subscription' => $subscriptionId,
                   'status' => 'open',
                   'created' => now()->timestamp,
                   'due_date' => now()->addDays(1)->timestamp,
                   'current_period_start' => now()->subMonth()->timestamp,
                   'current_period_end' => now()->timestamp,
                   'cancel_at' => null,
                   'lines' => [
                       'data' => [
                           [
                               'id' => 'il_'.Str::random(22),
                               'amount' => 1000,
                               'currency' => 'usd',
                               'period' => [
                                   'start' => now()->subMonth()->timestamp,
                                   'end' => now()->timestamp,
                               ],
                           ],
                       ],
                   ],
               ]
            ],
        ];
    }

    private function createStripeSubscription(Customer $customer, string $planId)
    {
        return StripeSubscription::constructFrom([
            'id' => 'sub_'.Str::random(22),
            'customer' => $customer->reference_id,
            'status' => 'active',
            'created' => now()->timestamp,
            'start_date' => now()->timestamp,
            'canceled_at' => null,
            'current_period_end' => now()->addMonth()->timestamp,
            'current_period_start' => now()->subMonth()->timestamp,
            'ended_at' => null,
            'items' => [
                'data' => [
                    [
                        'price' => [
                            'id' => $planId,
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function createStripeCustomer(Organization $org, BillingProvider $bsp)
    {
        $stripeCustomer =  StripeCustomer::constructFrom([
            'id' => 'cus_'.Str::random(22),
            'created' => now()->timestamp,
        ]);

        $twin = Twin::fromStripeObject($stripeCustomer);
        $twin->organization_id = $org->id;
        $twin->connector_id = $bsp->id;
        $twin->connector_type = 'billing_provider';
        $twin->save();

        $customer = new \App\Models\Account\Customer();
        $customer->organization_id = $org->id;
        $customer->reference_id = $stripeCustomer->id;
        $customer->billing_provider_id = $bsp->id;
        $customer->save();

        return $customer;
    }

}
