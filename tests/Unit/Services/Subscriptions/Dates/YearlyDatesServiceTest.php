<?php

namespace Tests\Unit\Services\Subscriptions\Dates;

use App\Models\Billing\Subscription;
use App\Models\Management\Organization;
use App\Models\Pricing\Plan;
use App\Services\Subscriptions\Dates\YearlyDatesService;
use Carbon\Carbon;
use Tests\TestCase;

class YearlyDatesServiceTest extends TestCase
{
    private Subscription $subscription;
    private Plan $plan;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();

        $this->plan = Plan::factory()->create([
            'invoice_interval' => 'P1Y',
        ]);

        $this->subscription = Subscription::factory()->create([
            'plan_id' => $this->plan->id,
            'organization_id' => $this->organization->id,
            'start_at' => Carbon::create(2024, 1, 25), // Anniversary day is 25th
        ]);
    }

    /** @test */
    public function it_computes_charges_from_date_correctly()
    {
        // Test case 1: billingAt is after anniversary day
        $billingAt = Carbon::create(2025, 5, 26);
        $service = new YearlyDatesService($this->subscription, $billingAt, false);

        $result = $service->computeChargesFromDate();
        $this->assertEquals('2025-05-25', $result->format('Y-m-d'));

        // Test case 2: billingAt is before anniversary day
        $billingAt = Carbon::create(2025, 5, 23);
        $service = new YearlyDatesService($this->subscription, $billingAt, false);

        $result = $service->computeChargesFromDate();
        $this->assertEquals('2025-04-25', $result->format('Y-m-d'));
    }

    /** @test */
    public function it_computes_charges_to_date_correctly()
    {
        // Test case 1: billingAt is after anniversary day
        $billingAt = Carbon::create(2025, 5, 26);
        $service = new YearlyDatesService($this->subscription, $billingAt, false);

        $result = $service->computeChargesToDate();
        $this->assertEquals('2025-06-24', $result->format('Y-m-d'));

        // Test case 2: billingAt is before anniversary day
        $billingAt = Carbon::create(2025, 5, 23);
        $service = new YearlyDatesService($this->subscription, $billingAt, false);

        $result = $service->computeChargesToDate();
        $this->assertEquals('2025-05-24', $result->format('Y-m-d'));
    }

    /** @test */
    public function it_handles_year_boundary_correctly()
    {
        // Test case: billingAt is in December
        $billingAt = Carbon::create(2025, 12, 26);
        $service = new YearlyDatesService($this->subscription, $billingAt, false);

        $fromDate = $service->computeChargesFromDate();
        $toDate = $service->computeChargesToDate();

        $this->assertEquals('2025-12-25', $fromDate->format('Y-m-d'));
        $this->assertEquals('2026-01-24', $toDate->format('Y-m-d'));
    }
}
