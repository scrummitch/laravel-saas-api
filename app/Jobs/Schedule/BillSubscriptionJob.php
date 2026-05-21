<?php

namespace App\Jobs\Schedule;

use App\Jobs\QueueableJob;
use App\Models\Billing\Subscription;
use App\Notifications\ShouldCreateInvoiceNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Stripe\Invoice;
use Stripe\Stripe;

class BillSubscriptionJob extends QueueableJob
{
    public function __construct(
        public Collection $subscriptions,
        public Carbon $billingAt,
        public string $invoicingReason
    )
    {
    }

    public function handle()
    {
        $subscriptions = Subscription::query()
            ->whereIn('id', $this->subscriptions->pluck('id'))
            ->with(['twin'])
            ->get();

        $subscriptions
            ->filter(function (Subscription $subscription) {
                return $subscription->plan->renew_interval->eq('P1Y')
                    && $subscription->plan->invoice_interval->eq('P1M');
            })
            ->each(function (Subscription $subscription) {

                $bsp = $subscription->customer->billingProvider;

                if (app()->runningUnitTests()) {
                    Invoice::create([
                        'customer' => $subscription->customer->reference_id,
                    ], [
                        'api_key' => $bsp->secret,
                        'stripe_account' => $bsp->external_id,
                    ]);

                    return;
                }

//                Notification::route('slack', config('services.slack.webhook_url'))
//                    ->notify(new ShouldCreateInvoiceNotification(
//                        subscription: $subscription,
//                        billingAt: $this->billingAt,
//                        invoicingReason: $this->invoicingReason,
//                    ));
            });

    }
}
