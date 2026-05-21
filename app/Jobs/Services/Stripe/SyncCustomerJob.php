<?php

namespace App\Jobs\Services\Stripe;

use App\Jobs\QueueableJob;
use App\Models\Account\Customer;

class SyncCustomerJob extends QueueableJob
{
    public function __construct(public Customer $customer) {}

    public function handle()
    {
        $this->customer->syncStripe(force: true);
    }
}
