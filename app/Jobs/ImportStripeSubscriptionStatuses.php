<?php

namespace App\Jobs;

use App\Models\Account\Customer;
use App\Models\Billing\Schedule;
use App\Models\Billing\Subscription;
use App\Models\Management\Organization;
use App\Models\Pricing\Plan;
use App\Models\Twin;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ImportStripeSubscriptionStatuses extends QueueableJob implements ShouldBeUnique
{
    public $timeout = 3600;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Subscription::unguard();

        Twin::query()
            ->join('billing_subscriptions', 'billing_subscriptions.twin_id', '=', 'twins.id')
            ->update([
                'linkable_type' => 'schedule',
                'linkable_id' => DB::raw('billing_subscriptions.schedule_id')
            ]);

        $total = Twin::query()
            ->where('type', \Stripe\Subscription::class)
            ->count();
        $processed = 0;

        logger()->info(logname('chunk-start'), [
            'total' => $total,
        ]);

        Twin::query()
            ->where('type', \Stripe\Subscription::class)
            ->with(['linkable', 'organization'])
            ->orderBy('id')
            ->chunk(100, function (Collection $collection) use ($total, &$processed, &$lastPercentage) {
                $collection->each(function ($twin) {
                    $this->processSubscription($twin);
                });

                $processed += $collection->count();
                $percentage = round(($processed / $total) * 100, 1);
                logger()->info(logname('chunk-processed'), [
                    'processed' => $processed,
                    'percentage' => $percentage,
                ]);
            });
        Subscription::reguard();
    }

    public function uniqueId(): string
    {
        return 'subscription_migration';
    }

    protected function processSubscription(Twin $twin)
    {
        $stripeSubscription = $twin->object();

        if (!$stripeSubscription->customer) {
            return;
        }

        $customer = $this->getCustomer($stripeSubscription->customer, $twin->organization_id);

        if (is_null($customer)) {
            return;
        }

        foreach ($stripeSubscription->items->data as $item) {
            $this->createOrUpdateSubscription($twin, $stripeSubscription, $item, $customer);
        }
    }

    protected function createOrUpdateSubscription(Twin $twin, $stripeSubscription, $item, Customer $customer): void
    {
        $planId = $this->getPlanId($twin->organization, data_get($item, 'price.id', data_get($item, 'plan.id')));

        if (is_null($planId)) {
            return;
        }

        /* @var Subscription $sub */
        $schedule = $customer->schedule;

        if (is_null($twin->linkable) || $twin->linkable instanceof Subscription) {
            $twin->link($schedule);
        }

        foreach ($schedule->subscriptions as $sub) {
            $updates = [
                'organization_id' => $customer->organization_id,
                'customer_id' => $customer->id,
                'quantity' => $item->quantity ?? 1,
                'current_state' => $this->mapStatus($stripeSubscription->status),
                'created_at' => Carbon::createFromTimestampUTC($stripeSubscription->created),
                'start_at' => Carbon::createFromTimestampUTC($stripeSubscription->start_date),
                'end_at' => $stripeSubscription->ended_at ? Carbon::createFromTimestampUTC($stripeSubscription->ended_at) : null,
                'cancel_at' => $stripeSubscription->status === 'canceled' ? Carbon::createFromTimestampUTC($stripeSubscription->current_period_end) : null,
            ];
            Subscription::unguarded(fn () => $sub->update($updates));
            $sub->loadMissing(['customer']);
            $this->updateInvoicedAndRenewedAt($sub, $stripeSubscription);
            $this->updateUpdatedAt($sub, $stripeSubscription);
            $sub->save();
        }
    }

    protected function getCustomer(string $stripeCustomerId, int $organizationId): ?Customer
    {
        return Customer::query()
            ->where('organization_id', $organizationId)
            ->where('reference_id', $stripeCustomerId)
            ->first();
    }

    protected function getPlanId(Organization $organization, ?string $stripePlanId): ?int
    {
        if (is_null($stripePlanId)) {
            return null;
        }

        $twin = Twin::query()
            ->where('organization_id', $organization->id)
            ->where('reference_id', $stripePlanId)
            ->where('type', \Stripe\Price::class)
            ->first();

        if (! $twin) {
            return null;
        }

        return Plan::query()
            ->where('organization_id', $organization->id)
            ->whereHas('charges', function ($query) use ($twin) {
                $query->where('billing_charges.id', $twin->linkable_id);
            })
            ->value('id');
    }

    protected function mapStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'incomplete' => 'pending',
            'incomplete_expired' => 'terminated',
            'trialing', 'active', 'past_due' => 'active',
            'canceled', 'unpaid' => 'cancelled',
            'paused' => 'paused',
            default => 'unknown',
        };
    }

    protected function updateInvoicedAndRenewedAt(Subscription $subscription, $stripeSubscription): void
    {
        $billing = $subscription->customer->billingProvider;

        $stripe = $billing->connector->getStripeClient();

        $invoices = $stripe->invoices->all([
            'customer' => $stripeSubscription->customer,
            'limit' => 10,
            'status' => 'paid',
        ]);

        foreach ($invoices->data as $invoice) {
            if ($invoice->subscription === $stripeSubscription->id) {
                $subscription->renewed_at = Carbon::createFromTimestampUTC($invoice->created);
            }

            if (!$subscription->invoiced_at || Carbon::createFromTimestampUTC($invoice->created)->isAfter($subscription->invoiced_at)) {
                $subscription->invoiced_at = Carbon::createFromTimestampUTC($invoice->created);
            }
        }
    }

    protected function updateUpdatedAt(Subscription $subscription, $stripeSubscription): void
    {
        // Stripe doesn't provide a direct 'updated_at' field, so we'll use the most recent of several relevant timestamps
        $relevantDates = [
            $stripeSubscription->created,
            $stripeSubscription->current_period_start,
            $stripeSubscription->current_period_end,
            $stripeSubscription->canceled_at,
        ];

        $mostRecentTimestamp = max(array_filter($relevantDates));
        $subscription->updated_at = Carbon::createFromTimestampUTC($mostRecentTimestamp);
    }
}
