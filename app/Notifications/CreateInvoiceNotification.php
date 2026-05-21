<?php

namespace App\Notifications;

use App\Models\Account\Customer;
use App\Models\Billing\BillingProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class CreateInvoiceNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected array $boundaries,
        protected string $amount,
        protected string $invoiceId,
        protected BillingProvider $billing,
        protected Customer $customer
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
            ->content('New invoice created!')
            ->attachment(function ($attachment) {
                $attachment->title('Invoice Details')
                    ->fields([
                        'Customer Reference ID' => $this->customer->reference_id,
                        'Billing Provider' => $this->billing->getRouteKey(),
                        'Invoice ID' => $this->invoiceId,
                        'Amount' => '$' . $this->amount,
                        'Boundaries' => json_encode($this->boundaries, JSON_PRETTY_PRINT),
                    ])
                    ->color('#36a64f');
            });
    }
}
