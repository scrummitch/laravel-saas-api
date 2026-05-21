<?php

namespace App\Http\Controllers\Client;

use App\Billing\Coupon;
use App\Client\ClientAuthorization;
use App\Database\Model;
use App\Models\Account\Agent;
use App\Models\Account\Customer;
use App\Models\Client;
use App\Models\Intelligence\Scenario;
use App\Models\Pricing\Package;
use App\Models\Pricing\Plan;
use App\Models\Store\Purchase;
use App\Models\Twin;
use App\Models\Values\PlanStatus;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Money\Currency;

class CreateCheckoutService extends BaseService
{
    public function __invoke(Client $client, Scenario $scenario, ?Agent $agent, ?Customer $customer)
    {
        abort_unless($client->billing_provider_id, 400, 'Client has no billing provider');

        $purchase = new Purchase();
        $purchase->organization_id = $client->organization_id;
        $purchase->customer_id = $customer?->id;
        $purchase->provider_id = Str::random(32);
        $purchase->provider_name = 'plandalf';
        $purchase->intent = $scenario->intent;

        // Get the raw items first - we need these to determine currency
        $rawItems = $scenario->bundleItems->pluck('purchasable');

        // Determine currency before processing items
        $purchase->currency = $this->determinePurchaseCurrency(
            $customer?->currency,
            $client->organization->default_currency,
            $rawItems
        );

        $currency = $purchase->currency;

        $coupon = $customer?->coupon;

        if ($agent?->is_sandbox_user && $client->environment === 'live') {
            $coupon = Twin::query()
                ->where('reference_id', Coupon::PLANDALF_SANDBOX_CODE)
                ->where('organization_id', $agent->organization_id)
                ->first();
        }

        $renewInterval = $customer?->getCurrentBillingInterval()
            ?? $scenario->renew_interval;

        // Now process items with determined currency
        $items = $rawItems->map(function (Model $purchasable) use ($currency) {
            return match (get_class($purchasable)) {
                Plan::class => $this->validatePlanCurrency($purchasable, $currency),
                Package::class => $this->getPackagePlan($purchasable->item, $currency),
            };
        });

        // Fix the renewal interval check
        if ($renewInterval->ne($items->first()?->renew_interval)) {
            [$purchase, $items] = $this->fixRenewInterval($purchase, $items, $renewInterval);
        }

        $purchase->renew_interval = $renewInterval;
        $purchase->billing_provider_id = $client->billing_provider_id;
        $purchase->save();

        foreach ($items as $item) {
            $purchase->items()->create([
                'purchasable_id' => $item->id,
                'purchasable_type' => Relation::getMorphAlias(get_class($item)),
                'quantity' => $this->getItemQuantity($item, $customer)
            ]);
        }

        if (!is_null($coupon)) {
            $purchase->applyCoupon($coupon);
        }

        return $purchase;
    }

    private function determinePurchaseCurrency(
        ?Currency $customerCurrency,
        ?Currency $organizationCurrency,
        \Illuminate\Support\Collection $items
    ): Currency {
        // First check if any Plan in items has a set currency
        $planCurrency = $items
            ->whereInstanceOf(Plan::class)
            ->map(fn(Plan $plan) => $plan->currency)
            ->filter()
            ->first();

        return $planCurrency
            ?? $customerCurrency
            ?? $organizationCurrency
            ?? new Currency('USD');
    }

    private function validatePlanCurrency(Plan $plan, \Money\Currency $targetCurrency): Plan
    {
        if ($plan->currency && !$plan->currency->equals($targetCurrency)) {
            // Try to find equivalent plan in target currency
            $alternatePlan = Plan::query()
                ->where('type', $plan->type)
                ->where('organization_id', $plan->organization_id)
                ->where('package_id', $plan->package_id)
                ->where('renew_interval', $plan->renew_interval->spec())
                ->where('currency', $targetCurrency->getCode())
                ->where('status', PlanStatus::Active)
                ->first();

            if ($alternatePlan) {
                return $alternatePlan;
            }
        }

        return $plan;
    }

    private function getPackagePlan(Package $package, Currency $currency): Plan
    {
        return $package
            ->plans()
            ->where('currency_code', $currency->getCode())
            ->where('is_active', true)
            ->first();
    }

    public static function getItemQuantity(Plan $plan, ?Customer $customer): int
    {
        // Find the in_advance inclusion for the plan
        $inclusion = $plan->inclusions
            ->firstWhere('charge.mode', 'in_advance');

        // Get customer summary if customer exists and metric is available
        $summary = $customer && $inclusion?->metric
            ? $inclusion->metric->customerSummary($customer)
            : null;

        // Return current aggregation or default to 1
        return $summary?->current_aggregation ?? 1;
    }


    private function fixRenewInterval(Purchase $purchase, \Illuminate\Support\Collection $items, mixed $renewInterval)
    {
        // Keep track of the updated items
        $updatedItems = collect();

        foreach ($items as $plan) {
            /* @var Plan $plan */
            // Skip if plan already matches the desired interval
            if ($plan->renew_interval->eq($renewInterval)) {
                $updatedItems->push($plan);
                continue;
            }

            // Find a matching plan with the correct interval
            $matchingPlan = Plan::query()
                ->where('organization_id', $purchase->organization_id)
                ->where('package_id', $plan->package_id)
                ->where('currency', $plan->currency->getCode())
                ->where('status', PlanStatus::Active)
                ->where('renew_interval', $renewInterval->spec())
                ->first();

            if ($matchingPlan) {
                // Use the matching plan
                $updatedItems->push($matchingPlan);
                continue;
            }

            // If no matching plan found, keep the original
            $updatedItems->push($plan);
        }

        // Update the purchase renewal interval
        $purchase->renew_interval = $renewInterval;

        return [$purchase, $updatedItems];
    }

}
