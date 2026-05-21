<?php

namespace Tests;

use App\Models\Billing\Charge;
use App\Models\Catalog\Inclusion;
use App\Models\Catalog\Product;
use App\Models\Management\Organization;
use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use App\Models\Pricing\Scheme;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Helper\UserHelper;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, UserHelper;

    public function flindev()
    {
        $org = Organization::query()
            ->where('name', 'flindev')
            ->first();

        if (is_null($org)) {
            $org = Organization::factory()
                ->flindev()
                ->create();

            $this->seed(AdminSeeder::class);
        }

        return $org;
    }

    protected function fillOrgWithProducts(Organization $org)
    {
        $scheme = Scheme::factory()
            ->for($org)
            ->create();

        $product = Product::factory()
            ->for($org)
            ->create();
        $charge = Charge::factory()
            ->for($product)
            ->for($org)
            ->create();
        $package = Package::factory()
            ->for($scheme)
            ->create();
        $plan = Plan::factory()
            ->for($package)
            ->for($org)
            ->create([
                'currency' => 'USD',
            ]);
        Inclusion::factory()
            ->for($plan)
            ->for($product)
            ->for($charge, 'charge')
            ->create();
    }
}
