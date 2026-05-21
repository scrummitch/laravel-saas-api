<?php

namespace App\Services\Invoices;

use App\Models\Account\Customer;
use App\Models\Billing\Subscription;
use App\Services\Subscriptions\DatesService;

class CustomerUsageService
{
    public function __construct(
        public Customer $customer,
        public Subscription $subscription,
    )
    {
    }

    public function boundaries(): array
    {
        $dates = DatesService::instance(
            subscription: $this->subscription,
            billingAt: now(),
            wantsCurrentUsage: true
        );

        return [
            'from_datetime' => $dates->fromDatetime(),
            'to_datetime' => $dates->toDatetime(),
            'charges_from_datetime' => $dates->chargesFromDatetime(),
            'charges_to_datetime' => $dates->chargesToDatetime(),
        ];
    }
}
