<?php

namespace App\Jobs\Services\Stripe;

use App\Models\Account\Customer;
use App\Models\Billing\Subscription;
use App\Models\Pricing\Plan;
use App\Models\Twin;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Stripe\Customer as StripeCustomer;
use Stripe\Price;
use Stripe\Price as StripePrice;
use Stripe\Subscription as StripeSubscription;

class ImportStripeSubscriptionsJob extends StripeImportJob
{
    public $timeout = 3600;

    protected array $prices = [];

    public function handle()
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $startTime = Carbon::now();

        $before = DB::table('twins')
            ->where('connector_id', $this->billing->id)
            ->where('connector_type', 'billing_provider')
            ->where('type', StripeSubscription::class)
            ->orderBy('reference_created_at')
            ->value('reference_id');

        logger()->info(logname(), [
            'before' => $before,
            'bsp_id' => $this->billing->id,
            'starting_after' => $before,
        ]);

        $totalInsertions = 0;

        $this->connector->fetchAndUpsert(
            $this->stripeclient->subscriptions->all(array_filter([
                'limit' => 100,
                'status' => 'all',
                'starting_after' => $before === '' ? null : $before,
            ])),
            function (Collection $subscriptions) use (&$startTime, &$totalInsertions) {
                $this->connector->upsertMany($subscriptions, StripeSubscription::class);

                $meta = [
                    'amount_subscription_insertions' => $totalInsertions,
                    'time_subscription_estimate' => $this->estimateTimeRemaining(
                        Arr::get($this->operation(), 'metadata.total_count_subscriptions', 0),
                        $totalInsertions,
                        $startTime->diffInSeconds(),
                        2
                    ),
                    'time_subscription_total' => $startTime->diffInSeconds(),
                ];

                $this->setMetadata($meta);
            }
        );

        $query = DB::table('twins')
            ->where('connector_id', $this->billing->id)
            ->where('type', StripeSubscription::class)
            ->whereNull('linkable_id');

        foreach ($this->cursorChunked($query, 50) as $chunk) {
            $this->importMultipleSubscriptions($chunk);

            $totalInsertions = $totalInsertions + count($chunk);
            $meta = [
                'amount_subscription_insertions' => $totalInsertions,
                'time_subscription_estimate' => $this->estimateTimeRemaining(
                    Arr::get($this->operation(), 'metadata.total_count_subscriptions', 0),
                    $totalInsertions,
                    $startTime->diffInSeconds()
                ),
                'time_subscription_total' => $startTime->diffInSeconds(),
            ];

            $this->setMetadata($meta);
        }

        $totalSubscriptionsCount = DB::table('twins')
            ->where('connector_id', $this->billing->id)
            ->where('type', StripeCustomer::class)
            ->count();

        $this->setMetadata('total_subscription_count', $totalSubscriptionsCount);
    }

    protected function importMultipleSubscriptions(array $subscriptionObjects): void
    {
        foreach ($subscriptionObjects as $subscriptionObject) {
            $this->importSubscription($subscriptionObject);
        }
    }

    protected function importSubscription(object $subscriptionObject): void
    {
        /* @var StripeSubscription $stripeSubscription */
        $stripeSubscription = StripeSubscription::constructFrom(json_decode($subscriptionObject->data, true));

        if (empty($stripeSubscription->customer)) {
            $this->failNoCustomer($stripeSubscription, 'not_attached');

            return;
        }

        $customer = $this->ensureCustomerIsCreated($stripeSubscription->customer);

        if (is_null($customer)) {
            $this->failNoCustomer($stripeSubscription, 'not_created');

            return;
        }

        $this->connector->syncSubscriptions($customer);
    }

    protected function ensureCustomerIsCreated(StripeCustomer|string $stripeCustomer): ?Customer
    {
        /* @var Twin $twin */
        $twin = Twin::query()
            ->where('connector_id', $this->billing->id)
            ->where('type', StripeCustomer::class)
            ->where('reference_id', $stripeCustomer)
            ->with(['linkable'])
            ->first();

        if (! is_null($twin) && empty($twin->linkable)) {
            logger()->info(logname('no-link'), [
                'customer_id' => $stripeCustomer,
            ]);

            return null;
        }

        if (is_null($twin?->linkable)) {
            logger()->info(logname('first-link-null'), [
                'customer_id' => $stripeCustomer,
            ]);

            return null;
        }

        if (! is_null($twin) && ! empty($twin->linkable)) {
            return $twin->linkable;
        }

        if (is_string($stripeCustomer)) {
            $stripeCustomer = StripeCustomer::retrieve($stripeCustomer, [
                'api_key' => $this->billing->secret,
                'stripe_account' => $this->billing->getRouteKey(),
            ]);

            if (is_null($stripeCustomer)) {
                throw new \Exception('Customer not found');
            }
        }

        $twin = $this->connector->upsert($stripeCustomer);

        Customer::unguard();
        $customer = Customer::query()
            ->firstOrNew([
                'organization_id' => $this->billing->organization_id,
                'connector_id' => $this->billing->id,
                'reference_id' => $twin->reference_id,
            ]);

        $customer->fill([
            'email' => $stripeCustomer->email,
            'name' => $stripeCustomer->name,
        ]);

        $customer->saveQuietly();

        $twin->link($customer);

        return $customer;
    }

    private function failNoCustomer(StripeSubscription $stripeSubscription, string $message): void
    {
        logger()->error(logname('fail'), [
            'message' => $message,
            'subscription_id' => $stripeSubscription->id,
        ]);
    }
}
