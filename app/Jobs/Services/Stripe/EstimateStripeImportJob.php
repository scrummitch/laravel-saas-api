<?php

namespace App\Jobs\Services\Stripe;

use Illuminate\Bus\Batchable;

class EstimateStripeImportJob extends StripeImportJob
{
    use Batchable;

    public function handle()
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $customerCountRes = $this->stripeclient->customers->all([
            'limit' => 1,
            'include[]' => 'total_count',
        ]);
        $subscriptionCountRes = $this->stripeclient->subscriptions->all([
            'status' => 'all',
            'limit' => 1,
            'include[]' => 'total_count',
        ]);

        $this->setMetadata([
            'total_count_customers' => $customerCountRes->total_count,
            'total_count_subscriptions' => $subscriptionCountRes->total_count,
        ]);
    }
}
