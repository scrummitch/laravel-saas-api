<?php

namespace App\Notifications;

use App\Models\Billing\Subscription;
use App\Models\Account\Customer;
use App\Models\Billing\BillingProvider;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class ShouldCreateInvoiceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Subscription $subscription,
        protected Carbon $billingAt,
        protected string $invoicingReason,
    )
    {
    }

    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->from('The Eagle', ':eagle:')
            ->content('New invoice should be created!')
            ->attachment(function ($attachment) {
                $attachment->title('Invoice Details')
                    ->fields([
                        'Customer' => 'https://app.plandalf.com/customers/' . $this->subscription->customer->getRouteKey(),
                        'BSP' => $this->subscription->customer->billingProvider->getRouteKey(),
                        'Subscription' => $this->subscription->getRouteKey(),
                        'BillingAt' => $this->billingAt->toDateTimeString(),
                        'Reason' => $this->invoicingReason,
                    ])
                    ->color('#36a64f');
            });
    }
}
