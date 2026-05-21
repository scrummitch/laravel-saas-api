<?php

namespace Tests\Feature;

use App\Jobs\ResetDemoAccountJob;
use App\Models\Account\Customer;
use App\Models\Billing\Charge;
use App\Models\Catalog\Inclusion;
use App\Models\Catalog\Product;
use App\Models\Convert\Flow;
use App\Models\Intelligence\Activity;
use App\Models\Intelligence\Scenario;
use App\Models\Pricing\Plan;
use App\Models\Pricing\Scheme;
use Tests\TestCase;

class DemoAccountJobTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_loading(): void
    {
        $user = $this->createUser();
        $org = $user->currentOrganization;
        $org->test_billing_provider_id = 1;
        $org->save();

        $soloPlan = Plan::factory()
            ->for($org)
            ->create([
                'currency' => 'USD',
                'display_name' => 'Single Hobbit Plan',
            ]);
        $teamPlan = Plan::factory()
            ->for($org)
            ->create([
                'currency' => 'USD',
                'display_name' => 'Fellowship Plan',
            ]);
        $soloProduct = Product::factory()
            ->for($org)
            ->create([
                'name' => 'Solo Plan',
            ]);
        $proProduct = Product::factory()
            ->for($org)
            ->create([
                'name' => 'Pro Plan',
            ]);
        $soloCharge = Charge::factory()
            ->for($org)
            ->for($soloProduct)
            ->create([
                'amount' => random_int(2000, 8000),
                'currency' => 'USD',
            ]);
        $teamCharge = Charge::factory()
            ->for($org)
            ->for($proProduct)
            ->create([
                'amount' => random_int(3000, 12000),
                'currency' => 'USD',
            ]);
        Inclusion::unguard();
        $soloPlan->inclusions()->create([
            'charge_id' => $soloCharge->id,
        ]);
        $teamPlan->inclusions()->create([
            'charge_id' => $teamCharge->id,
        ]);
        Inclusion::reguard();

        config()->set('app.demo_organization', $org->getRouteKey());

        $workflow = Flow::factory()
            ->for($org)
            ->create([
                'name' => 'Demo Workflow',
            ]);

        $scheme = Scheme::factory()
            ->for($org)
            ->create();

        $paywall1 = Scenario::factory()
            ->for($workflow)
            ->for($org)
            ->for($scheme)
            ->create([
                'display_name' => 'Orb Solo',
            ]);

        $paywall2 = Scenario::factory()
            ->for($workflow)
            ->for($org)
            ->for($scheme)
            ->create([
                'display_name' => 'Orb Fellowship',
            ]);

        // see the org has sessions!
        $this->assertNotEmpty($org->scenarios);

        Customer::factory()
            ->for($org)
            ->create();

        dispatch_sync(new ResetDemoAccountJob());


        $activities = Activity::query()
            ->whereIn('scenario_id', $workflow->scenarios->pluck('id'))
            ->get();

        $this->assertNotEmpty($activities);
    }
}
