<?php

namespace Tests\Feature;

use App\Models\Account\Agent;
use App\Models\Account\Customer;
use App\Models\Billing\Schedule;
use App\Models\Billing\Subscription;
use App\Models\Catalog\Inclusion;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductFamily;
use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use App\Models\Pricing\Scheme;
use Illuminate\Support\Arr;
use Tests\TestCase;

class SdkFiltersTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_random_examples_from_sdk(): void
    {
        $org = $this->createOrg();
        $client = $org->clients()->first();
        $scheme = Scheme::factory()->for($org)->create();
        $customer = Customer::factory()
            ->for($org)
            ->create();
        $family = ProductFamily::factory()
            ->for($org)
            ->create();
        $product = Product::factory()
            ->for($org)
            ->for($family)
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
            ->create();
        Inclusion::factory()
            ->for($plan)
            ->for($product)
            ->create([

            ]);

        Subscription::factory()
        ->active()
        ->for($customer)
        ->for($org)
        ->for($plan)
        ->create([
            'quantity' => 1,
        ]);
        // attach plan to scheme (hasManyThrough)
        /* @var Package $package */
        Package::unguard();
        $package = $scheme->packages()->create([]);
        Package::reguard();
        $plan->package()->associate($package)->save();
        $plan->refresh();

        $this->assertNotNull($plan->package);


        $getSessionRes = $this->getJson('/client/session', [
            'Authorization' => 'Bearer '.$client->generateAgentJwt('test', ['customer' => $customer->getRouteKey()]),
        ]);
        $data = $getSessionRes->json();

        $this->assertTrue($this->isSubscribedToPlan($data, ['planId' => $plan->getRouteKey()]));
        $this->assertFalse($this->isSubscribedToPlan($data, ['planId' => ['something']]));
        $this->assertTrue($this->isSubscribedToProduct($data, [$product->getRouteKey()]));
        $this->assertFalse($this->isSubscribedToProduct($data, ['scooby-doo']));
        $this->assertTrue($this->isSubscribedToPackage($data, [$package->getRouteKey()]));
        $this->assertFalse($this->isSubscribedToPackage($data, ['pee-pee-poo-poo']));
        $this->assertTrue($this->hasMatchScheduleQuantity($data, $family, 1));
        $this->assertFalse($this->hasMatchScheduleQuantity($data, $family, 2));
        $this->assertTrue($this->checkProductSubscriptions($data, ['value' => [$product->lookup_key], 'op' => 'in']));
        $this->assertFalse($this->checkProductSubscriptions($data, ['value' => [$product->lookup_key], 'op' => 'notIn']));
    }

    private function isSubscribedToPlan(array $data, array $filters): bool
    {
        return collect($data['subscriptions'])
            ->where('plan.id', Arr::get($filters, 'planId'))
            ->isNotEmpty();
    }

    private function isSubscribedToProduct(array $data, array $filters): bool
    {
        return collect($data['subscriptions'])
            ->filter(function ($subscription) use ($filters) {
                return collect($subscription['plan']['products'])
                    ->filter(function ($product) use ($filters) {
                        return in_array($product['id'], $filters);
                    })
                    ->isNotEmpty();
            })
            ->isNotEmpty();
    }

    private function isSubscribedToPackage(array $data, array $array): bool
    {
        return collect($data['subscriptions'])
            ->filter(function ($subscription) use ($array) {
                return in_array($subscription['plan']['package']['id'], $array);
            })
            ->isNotEmpty();
    }

    private function hasMatchScheduleQuantity(array $data, ProductFamily $family, int $amount): bool
    {
        return collect($data['subscriptions'])
            ->filter(function ($subscription) use ($family, $amount) {
                return $subscription['quantity'] === $amount
                    && $subscription['plan']['product_family']['lookup_key'] === $family->getRouteKey();
            })
            ->isNotEmpty();
    }

    private function checkProductSubscriptions(array $data, array $filters): bool
    {
        $productIds = collect($data['subscriptions'])
            ->map(function ($subscription) {
                return $subscription['plan']['product_key'];
            });

        return $this->compareByComparisonOperator($productIds, $filters['value'], $filters['op']);
    }

    private function compareByComparisonOperator(\Illuminate\Support\Collection $productIds, mixed $value, mixed $op)
    {
        switch ($op) {
            case 'in':
                return $productIds->intersect($value)->isNotEmpty();
            case 'not_in':
                return $productIds->intersect($value)->isEmpty();
            case 'eq':
                return $productIds->count() === $value;
            case 'neq':
                return $productIds->count() !== $value;
            case 'gt':
                return $productIds->count() > $value;
            case 'gte':
                return $productIds->count() >= $value;
            case 'lt':
                return $productIds->count() < $value;
            case 'lte':
                return $productIds->count() <= $value;
            default:
                return false;
        }
    }
}
