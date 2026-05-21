<?php

namespace App\Integration\Connectors;

use App\Models\Account\Customer;
use App\Models\Billing\BillingProvider;
use App\Models\Billing\Schedule;
use App\Models\Billing\Subscription;
use App\Models\Catalog\Product;
use App\Models\Pricing\Plan;
use App\Models\Twin;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Sentry\Tracing\SpanContext;
use Stripe\Coupon;
use Stripe\Exception\InvalidRequestException;
use Stripe\Price;
use Stripe\StripeClient;
use Stripe\StripeObject;
use Stripe\Subscription as StripeSubscription;

class StripeConnector implements ConnectorInterface
{
    protected StripeClient $stripe;

    public function __construct(public BillingProvider $billing)
    {
        $this->stripe = new StripeClient([
            'api_key' => $this->billing->secret,
            'stripe_account' => $this->billing->lookup_key,
        ]);
    }

    public function upsertCoupon(string $code): ?Coupon
    {
        $coupon = null;

        try {
            $coupon = $this->stripe->coupons->retrieve($code, []);
        } catch (InvalidRequestException $requestException) {
            // nothing
        }

        if (empty($coupon)) {
            $coupon = $this->stripe->coupons->create([
                'percent_off' => 100,
                'duration' => 'forever',
                'name' => 'Plandalf Sandbox Discount',
                'id' => \App\Billing\Coupon::PLANDALF_SANDBOX_CODE,
            ]);
        }

        $this->upsert($coupon);

        return $coupon;
    }

    public function getStripeClient(): StripeClient
    {
        return $this->stripe;
    }

    public function findOrCreateProduct(Twin|string $twin): ?Product
    {
        if (is_string($twin)) {
            $twin = $this->fetchAndCreateProductTwin($twin);
        }

        $product = Product::upsertFromTwin($twin);

        $twin->link($product);

        return $product;
    }

    public function fetchAndCreateProductTwin(string $productId): ?Twin
    {
        /* @var Twin|null $twin */
        $twin = Twin::query()
            ->where('connector_id', $this->billing->id)
            ->where('type', \Stripe\Product::class)
            ->where('reference_id', $productId)
            ->first();

        if (! is_null($twin)) {
            return $twin;
        }

        $stripeProduct = $this->stripe->products->retrieve($productId);

        if (is_null($stripeProduct)) {
            return null;
        }

        return $this->upsert($stripeProduct);
    }

    public function createSubscription(array $params): ?StripeSubscription
    {
        return $this->stripe->subscriptions->create($params);
    }

    public function updateSubscription(string $id, array $params)
    {
        return $this->stripe->subscriptions->update($id, Arr::only($params, [
            'default_payment_method',
            'items',
        ]));
    }

    protected function mapObjects(LazyCollection $collection): LazyCollection
    {
        return $collection->map(fn ($o) => array_merge($o->toArray(), ['data' => json_encode($o->data)]));
    }

    public function fetchAndUpsert(\Stripe\Collection $stripeCollection, \Closure $upsert): void
    {
        LazyCollection::make(fn () => yield from $stripeCollection->autoPagingIterator())
            ->chunk(250)
            ->map(fn (LazyCollection $objects) => $upsert($objects->collect()))
            ->count();
    }

    public function upsert(StripeObject $object): ?Twin
    {
        if ($object instanceof \Stripe\Collection) {
            // Prevent us trying to save an entire collection
            return null;
        }

        return Twin::query()
            ->updateOrCreate([
                'reference_id' => $object->id,
                'connector_id' => $this->billing->id,
                'connector_type' => 'billing_provider',
                'organization_id' => $this->billing->organization_id,
            ], Twin::fromStripeObject($object)->toArray());
    }

    public function upsertMany(Collection $objects, string $type): void
    {
        $twins = $objects
            ->map(function (StripeObject $object) {
                $newTwin = Twin::fromStripeObject($object);
                $newTwin->connector()->associate($this->billing);
                $newTwin->organization()->associate($this->billing->organization);

                return $newTwin;
            });

        $i = DB::table('twins')
            ->insertOrIgnore(
                $twins->map(fn ($o) => array_merge($o->toArray(), ['data' => json_encode($o->data)]))->toArray()
            );

        $total = Twin::query()
            ->where('connector_id', $this->billing->id)
            ->where('connector_type', 'billing_provider')
            ->where('type', $type)
            ->count();

        logger()->info(logname(Str::plural($type)), [
            'total_count' => $total,
            'upsert_count' => $i,
        ]);
    }

    #[\Override]
    public function syncSubscriptions(Customer $customer): void
    {
        // get twin for this billing provider
        /** @var Twin */
        $twin = $customer
            ->twins()
            ->bsp($this->billing)
            ->first();

        if (! $twin) {
            logger()->error(logname(), [
                'message' => 'No twin found for customer',
                'customer_id' => $customer->id,
                'billing_provider_id' => $this->billing->id,
            ]);

            return;
        }

        $subscriptionsSpan = SpanContext::make()
            ->setOp(logname('stripe-subs-sync'))
            ->setDescription('Syncs stripe subscriptions');

        // 175ms
        $stripeSubscriptions = \Sentry\trace(function () use ($twin) {
            return $this
                ->getStripeClient()
                ->subscriptions
                ->all([
                    'customer' => $twin->reference_id,
                    'status' => 'all',
                ]);
        }, $subscriptionsSpan);

        $schedule = $customer->schedule ?? tap(new Schedule, function($schedule) use ($customer) {
            $schedule->customer_id = $customer->id;
            $schedule->organization_id = $customer->organization_id;
            $schedule->save();
        });

        // Keep track of processed subscription IDs for cleanup
        $processedSubscriptionIds = [];

        /** @var StripeSubscription $stripeSubscription */
        foreach ($stripeSubscriptions as $stripeSubscription) {
            // Create or update subscription twin
            $subTwin = Twin::query()->updateOrCreate(
                [
                    'reference_id' => $stripeSubscription->id,
                    'connector_id' => $twin->connector_id,
                    'connector_type' => $twin->connector_type,
                    'organization_id' => $customer->organization_id,
                ],
                Twin::fromStripeObject($stripeSubscription)->toArray()
            );

            // Process each item in the stripe subscription
            foreach ($stripeSubscription->items as $item) {
                // Find the corresponding price twin and linked charge
                $price = Twin::query()
                    ->where([
                        'type' => Price::class,
                        'reference_id' => is_string($item->price) ? $item->price : $item->price?->id,
                        'connector_id' => $twin->connector_id,
                        'connector_type' => $twin->connector_type,
                        'organization_id' => $customer->organization_id,
                    ])
                    ->with(['linkable'])
                    ->first();

                $charge = $price?->linkable;

                if (is_null($charge)) {
                    logger()->warning(logname(), [
                        'message' => 'No charge found for price',
                        'price_id' => $item->price?->id,
                        'customer_id' => $customer->id,
                    ]);
                    continue;
                }

                // Find the plan associated with this charge
                $plan = Plan::query()
                    ->whereHas('charges', function ($query) use ($charge) {
                        $query->where('billing_charges.id', $charge->id);
                    })
                    ->first();

                if (! $plan) {
                    logger()->warning(logname(), [
                        'message' => 'No plan found for charge',
                        'charge_id' => $charge->id,
                        'customer_id' => $customer->id,
                    ]);
                    continue;
                }

                Subscription::unguard();
                // Create or update subscription for this item
                $subscription = Subscription::query()
                    ->updateOrCreate([
                        'schedule_id' => $schedule->id,
                        'plan_id' => $plan->id,
                        'twin_id' => $subTwin->id,
                    ], [
                        'organization_id' => $customer->organization_id,
                        'customer_id' => $customer->id,
                        'quantity' => $item->quantity ?? 1,
                        'current_state' => Subscription::mapStripeStatusToState($stripeSubscription->status),

                        'created_at' => Carbon::createFromTimestamp($stripeSubscription->created),

                        'start_at' => $this->determineStartAt($stripeSubscription),
                        'end_at' => $stripeSubscription->ended_at ? Carbon::createFromTimestamp($stripeSubscription->ended_at) : null,
                        'cancel_at' => $stripeSubscription->cancel_at ? Carbon::createFromTimestamp($stripeSubscription->cancel_at) : null,
                        'renewed_at' => $stripeSubscription->current_period_start ? Carbon::createFromTimestamp($stripeSubscription->current_period_start) : null,
                    ]);

                Subscription::reguard();

                $processedSubscriptionIds[] = $subscription->id;
            }

            if (is_null($subTwin->linkable_id)) {
                $subTwin->link($schedule);
            }
        }

        // Handle subscriptions that no longer exist in Stripe
        Subscription::query()
            ->where('schedule_id', $schedule->id)
            ->whereNotIn('id', $processedSubscriptionIds)
            ->where('current_state', '!=', 'inactive')
            ->update([
                'current_state' => 'inactive',
                'end_at' => now(),
            ]);
    }

    protected function determineStartAt(StripeSubscription $subscription): Carbon
    {
        //Tuesday, 16 January 2024 17:21:55
        if ($subscription->billing_cycle_anchor) {
            return Carbon::createFromTimestamp($subscription->billing_cycle_anchor);
        }

        $now = Carbon::now();

        if (!empty($subscription->trial_end) && $now->lt(Carbon::createFromTimestampUTC($subscription->trial_end))) {
            return Carbon::createFromTimestampUTC($subscription->trial_end);
        }

        return Carbon::createFromTimestampUTC($subscription->start_date);
    }
}
