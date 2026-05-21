<?php

namespace App\Jobs\Schedule;

use App\Jobs\QueueableJob;
use App\Services\Subscriptions\BillingService;

class SubscriptionsBillerJob extends QueueableJob
{
    public function handle()
    {
        BillingService::dispatch_sync();
    }
}
