<?php

namespace Tests\Feature;

use App\Models\Account\Agent;
use App\Models\Account\Customer;
use App\Models\Billing\Schedule;
use App\Models\Pricing\Plan;
use App\Services\Invoices\CustomerUsageService;
use Tests\TestCase;

class DateServiceTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example_date_range(): void
    {
        $org = $this->createOrg();
        $customer = Customer::factory()
            ->for($org)
            ->create();
        $schedule = Schedule::factory()
            ->for($org)
            ->for($customer)
            ->create();
        $agent = Agent::factory()
            ->for($org)
            ->create();
        $agent->associateWithCustomer($customer);

        $plan = Plan::factory()
            ->create([
                'renew_interval' => 'P1M',
                'invoice_interval' => 'P1M',
                'billing_anchor' => 'anniversary',
            ]);
        $schedule->plans()->attach($plan, [
            'organization_id' => $org->getKey(),
            'customer_id' => $customer->id,
            'start_at' => now()->subWeek()->subDays(3),
            'end_at' => null,
        ]);

        $subscription = $customer->subscriptions->first();

        $usageService = new CustomerUsageService($customer, $subscription);
        $b = $usageService->boundaries();

        // from_datetime should be a week ago from today
        $this->assertEquals(
            now()->subWeek()->subDay(3)->startOfDay(),
            $b['from_datetime'],
        );

        // to_datetime should be the anniversary day of the subscription next month
        $this->assertEquals(
            $subscription->start_at->addMonth()->startOfMonth()->day($subscription->start_at->day)->subDay(),
            $b['to_datetime'],
        );

        //charges_from_datetime should be same as from_datetime for month billing
        $this->assertEquals(
            $b['from_datetime'],
            $b['charges_from_datetime'],
        );
        // charges_to_datetime should be the day before the anniversary day of the subscription next month at end of day
        $this->assertEquals(
            $subscription->start_at->addMonth()->startOfMonth()->day($subscription->start_at->day)->subDay()->endOfDay(),
            $b['charges_to_datetime'],
        );
    }
}
