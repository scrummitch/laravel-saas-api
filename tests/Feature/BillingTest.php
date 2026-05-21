<?php

namespace Tests\Feature;

use App\Models\Account\Customer;
use App\Models\Billing\Subscription;
use App\Models\Pricing\Plan;
use App\Services\Invoices\CustomerUsageService;
use App\Services\Subscriptions\BillingService;
use App\Services\Subscriptions\DatesService;
use Carbon\Carbon;
use Tests\TestCase;

class BillingTest extends TestCase
{
    public function test_billing_service()
    {
        $user = $this->createUser();
        $org = $this->createOrg($user);
        $org->timezone = '+00:00';
        $org->save();


        $customer = Customer::factory()
            ->for($org)
            ->create();
        $plan = Plan::factory()
            ->for($org)
            ->create([
                'status' => 'active',
                'type' => 'standard',
                'display_name' => 'Video Messaging Pro 2023 per year',
                'name' => 'usd-2023-vm-pro-yearly-1',
                'renew_interval' => 'P1Y',
                'invoice_interval' => 'P1M',
                'billing_anchor' => 'anniversary',
                'currency' => 'USD',
            ]);

        $subscription = Subscription::factory()
            ->for($plan)
            ->for($org)
            ->for($customer)
            ->create([
                'quantity' => 1,
                'current_state' => 'active',
                'start_at' => 1732680223,
                'renewed_at' => 1732680223,
            ]);

        $usage = new CustomerUsageService(
            $customer,
            $subscription,
        );

        $this->assertNotEmpty(
            $usage->boundaries()
        );

        $now = Carbon::make('2024-11-27T04:10:03+00:00');
        $this->travelTo($now);
        $service = new BillingService(billingAt: $now);

//         $this->assertNotEmpty($service->getBillableSubscriptions($now));

    }
}
