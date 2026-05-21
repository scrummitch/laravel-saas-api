<?php

namespace App\Services\Checkout;

use App\Jobs\Services\Stripe\SyncCustomerJob;
use App\Models\Billing\Charge;
use App\Models\Billing\Subscription;
use App\Models\Convert\Attribution;
use App\Models\Convert\CheckoutState;
use App\Models\Intelligence\ActionType;
use App\Models\Intelligence\ActivityAction;
use App\Models\Pricing\Plan;
use App\Models\Store\Purchase;
use App\Models\Store\PurchaseItem;
use App\Models\Twin;
use App\Models\Values\PlanStatus;
use App\Models\Values\PlanType;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stripe\SubscriptionItem;

class CommitCheckoutService extends BaseService
{
    public function __construct(
        public Purchase $purchase
    ) {
    }

    public function __invoke(array $data = [])
    {
        if ($this->purchase->isFinalised()) {
            return $this->purchase;
        }

        $customer = $this->purchase->customer;

        if (is_null($customer)) {
            // note: customer needs to be created first
            throw ValidationException::withMessages([
                'customer' => [
                    'No customer available',
                ],
            ]);
        }

        $billing = $customer->billingProvider;
        $pms = $customer->paymentMethods();

        if ($pms->isEmpty()) {
            throw ValidationException::withMessages([
                'payment_method' => [
                    'No payment method available',
                ],
            ]);
        }

        $pmTwin = $this->purchase->payment_method;

        $items = $this->purchase->items;

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'plans' => [
                    'No plans available',
                ],
            ]);
        }

        // interval
        $currentBillingInterval = $customer->getCurrentBillingInterval();
        $newBillingInterval = $this->purchase->renew_interval;
        $isChangingInterval = $currentBillingInterval?->notEqualTo($newBillingInterval);

        if ($isChangingInterval && ! $this->canSwitchInterval()) {
            throw ValidationException::withMessages([
                'interval' => [
                    'Cannot change interval',
                ],
            ]);
        }

        $lineItems = $items
            ->filter(fn (PurchaseItem $item) => !is_null($item->purchasable))
            ->map(function (PurchaseItem $item) {
                $twin = $item->purchasable->charges
                    ->first()
                    ?->twins()
                    ->where('connector_id', $this->purchase->billing_provider->id)
                    ->first();

                if (! $twin) {
                    throw ValidationException::withMessages([
                        'payment_method' => [
                            'Charge not found in billing provider',
                        ],
                    ]);
                }

                return [
                    'quantity' => $item->quantity,
                    'price' => $twin?->reference_id,
                ];
            })
            ->filter()
            ->keyBy('price');

        $stripe = $billing->connector->getStripeClient();

        // todo: we should have subs before we even get to this step
        $customerStripeSubscriptions = $customer->getStripeSubscriptions();

        $stripeSubscription = $customerStripeSubscriptions
            ->first();

        /** @var SubscriptionItem $subItem */
        foreach ($stripeSubscription?->items ?? [] as $subItem) {
            $twin = $billing
                ->twins()
                ->where('reference_id', $subItem->price->id)
                ->with('linkable')
                ->first();

            /* @var Charge $charge */
            $charge = $twin->linkable;

            if (! $charge) {
                throw ValidationException::withMessages([
                    'payment_method' => [
                        'Charge not found',
                    ],
                ]);
            }

            /* @var SubscriptionItem $subItem */
            $plan = Plan::query()
                ->whereHas('charges', fn ($q) => $q->where('billing_charges.id', $charge->id))
                ->first();

            // if the sub line item is in the new line items, then we dont need to do anything
            if ($lineItems->has($plan?->lookup_key)) {
                $lineItems->forget($plan->lookup_key);

                continue;
            }

            $priceId = null;

            //
            $currentProductFamilies = $plan->getProductFamilies();

            /**
             * If a standard plan is present in lineItems with the same module, replace the currentPlan
             *
             * @var Plan $equivalentStandardCheckoutPlan
             */
            $equivalentStandardCheckoutPlan = $items
                ->pluck('purchasable')
                ->first(function (Plan $plan) use ($currentProductFamilies) {
                    if ($plan->type !== PlanType::standard) {
                        return false;
                    }

                    return $plan->getProductFamilies()->intersect($currentProductFamilies)->isNotEmpty();
                });

            /**
             * Replace the current plan
             */
            if (! is_null($equivalentStandardCheckoutPlan)) {
                $priceId = $equivalentStandardCheckoutPlan
                    ->charges()
                    ->first()
                    ->twins()
                    ->where('connector_id', $billing->id)
                    ->value('reference_id');

                $lineItems->forget($priceId);
            } elseif ($isChangingInterval) {
                /**
                 * Change intervals of the plans that are not in lineItems
                 * ex.
                 * current interval is yearly and wants to change to monthly
                 * line_items have pro + growth monthly
                 * but they have another subscription called xyz addon
                 * we need to be able to move the xyz addon from yearly to monthly
                 */
                $plan = Plan::query()
                    ->where('package_id', $plan->package_id)
                    ->where('renew_interval', $newBillingInterval)
                    ->where('status', PlanStatus::Active->value)
                    ->first();

                if (! $plan) {
                    throw ValidationException::withMessages([
                        'interval' => [
                            'Cannot change interval',
                        ],
                    ]);
                }

                $priceId = $plan->charges()
                    ->first()
                    ?->twins()
                    ->where('connector_id', $billing->id)
                    ->first()
                    ->reference_id;
            }

            if ($priceId) {
                $lineItems->push([
                    'id' => $subItem->id,
                    'price' => $priceId,
                ]);
            }
        }

        $discount = DB::table('billing_discounts')
            ->where('discountable_type', 'purchase')
            ->where('discountable_id', $this->purchase->getKey())
            ->first();

        $coupon = $discount ? Twin::query()
            ->find($discount?->coupon_id) : null;

        $params = array_filter([
            'customer' => $customer->reference_id,
            'currency' => $this->purchase->currency->getCode(),
            'default_payment_method' => $pmTwin?->reference_id,
            'items' => $lineItems
                ->values()
                ->toArray(),
            'coupon' => $coupon?->reference_id,
        ]);

        /**
         * charge pro-rated amount immediately
         */
        if ($this->purchase->billing_start_at->isToday()) {
            $params['proration_behavior'] = 'always_invoice';
        }

        logger()->info(logname('stripe-params'), $params);

        // New checkout
        if ($customerStripeSubscriptions->isEmpty()) {
            $sub = $stripe->subscriptions->create($params);
        } else {
            $sub = $stripe->subscriptions->update($stripeSubscription->id, Arr::only($params, [
                'default_payment_method',
                'items',
                'proration_behavior',
            ]));
        }

        Twin::unguard();
        $subTwin = Twin::query()
            ->updateOrCreate([
                'reference_id' => $sub->id,
                'connector_id' => $billing->id,
                'connector_type' => 'billing_provider',
                'organization_id' => $billing->organization_id,
            ], Twin::fromStripeObject($sub)->toArray());
        Twin::reguard();

//        Signal::pending()
//            ->for($customer)
//            ->amount()
//            ->subscription($subscription)
//            ->at(now())
//            // effective date?
//            ->from($plans)
//            //
//            ->withMeta([
//
//            ]);
//            ->emit();

        $this->purchase->completed_at = Carbon::now();
        $this->purchase->current_state = CheckoutState::COMPLETED;
        $this->purchase->save();

        try {
            if ($subTwin && $this->purchase->activity) {
                $attribution = new Attribution();
                $attribution->producer()->associate($this->purchase->activity?->scenario);
                $attribution->activity_id = $this->purchase->activity_id;
                $attribution->type = 'upgrade';
                $attribution->currency = $this->purchase->currency;
                $attribution->purchase()->associate($subTwin);
                $attribution->session_id = 1;
                $attribution->save();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        dispatch(new SyncCustomerJob($customer));
        $customer->reference_synced_at = null;
        $customer->save();


        // fire an action
        if ($this->purchase->activity_id) {
             $action = new ActivityAction([
                'activity_id' => $this->purchase->activity_id,
                'event_id' => Str::uuid()->toString(),
                'event_name' => null,
                'type' => ActionType::Complete,
                'properties' => [

                ],
                'metadata' => [

                ],
                'created_at' => now()
             ]);
             $this->purchase->loadMissing(['activity']);
             $this->purchase->activity->apply($action);
        }

        return $this->purchase;
    }

    public function canSwitchInterval(): bool
    {
        $customer = $this->purchase->customer;

        if (is_null($customer)) {
            return true;
        }

        if (is_null($customer->schedule)) {
            return true;
        }

        $subscription = $customer->schedule
            ->subscriptions
            ->where('current_state', 'active')
            ->first();

        if ($subscription?->is_trial) {
            return true;
        }

        $scenario = $this->purchase->activity?->scenario;

        if (is_null($scenario)) {
            return true;
        }


        // this is expansion specific settings where we're changing a quantity OR adding an addon?

        if ($scenario->intent === 'expansion') {
            if (data_get($scenario->properties, 'can_change_interval_on_expansion', false)) {
                $isSubscribeToPayingPlan = $customer->schedule->subscriptions
                    ->first(function (Subscription $subscription) {
                        return $subscription->plan->type !== PlanType::provisional;
                    }) !== null;

                //allow free users to change plan
                if (! $isSubscribeToPayingPlan) {
                    return true;
                }
            }

            return false;
        } elseif ($scenario === 'upgrade') {
            return true;
        }

        return false;
    }

}

// TODO: The product prod_NeDHvS07cyhD9n is marked as inactive, and thus no new subscriptions can be create to any plans of this product. You provided the plan price_1Msur2FmvUKqVS2HSsf2D8tu.
// todo: in sdk, if commit mutation fails we need to reset and show some kind of error!

// checkout can work like stripe, or like canva
// embed checkouts will be different than modal checkouts
// todo: transition from free plans to paid
// todo: transition between renew intervals
// todo: use saved payment methods
// todo: adding addons
// todo: incrementing quantities
// TODO: The product prod_NeDHvS07cyhD9n is marked as inactive, and thus no new subscriptions can be create to any plans of this product. You provided the plan price_1Msur2FmvUKqVS2HSsf2D8tu.
// todo: in sdk, if commit mutation fails we need to reset and show some kind of error!

// checkout can work like stripe, or like canva
// embed checkouts will be different than modal checkouts
// todo: transition from free plans to paid
// todo: transition between renew intervals
// todo: use saved payment methods
// todo: adding addons
// todo: incrementing quantities
// intent = order -> purchase a product
// intent = upgrade, order, expansion
// inject "product", with price?
// type = service,

// todo: theres a pm for htis checkout we need to use rather than grabbing first
