<?php

namespace App\Jobs\Services\Stripe;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Stripe\Customer as StripeCustomer;

class ImportStripeCustomersJob extends StripeImportJob
{
    public $timeout = 3600;

    public function handle()
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $before = DB::table('twins')
            ->where('connector_id', $this->billing->id)
            ->where('connector_type', 'billing_provider')
            ->where('type', StripeCustomer::class)
            ->orderBy('reference_created_at')
            ->value('reference_id');

        $startTime = Carbon::now();
        $totalInsertions = 0;

        $this->connector->fetchAndUpsert(
            $this->stripeclient->customers->all(array_filter([
                'limit' => 100,
                'starting_after' => $before === '' ? null : $before,
            ])),
            function (Collection $customers) use (&$totalInsertions, &$startTime) {
                // insert
                $this->connector->upsertMany($customers, StripeCustomer::class);

                $totalInsertions = $totalInsertions + $customers->count();
                $meta = [
                    'amount_customer_insertions' => $totalInsertions,
                    'time_customer_estimate' => $this->estimateTimeRemaining(
                        Arr::get($this->operation(), 'metadata.total_count_customers', 0),
                        $totalInsertions,
                        $startTime->diffInSeconds()
                    ),
                    'time_customer_total' => $startTime->diffInSeconds(),
                ];

                $this->setMetadata($meta);
            }
        );

        $query = DB::table('twins')
            ->where('connector_id', $this->billing->id)
            ->where('type', StripeCustomer::class)
            ->whereNull('linkable_id');

        foreach ($this->cursorChunked($query, 50) as $chunk) {
            $this->importMultipleCustomers($chunk);
        }

        $totalCustomerCount = DB::table('twins')
            ->where('connector_id', $this->billing->id)
            ->where('type', StripeCustomer::class)
            ->count();

        $this->setMetadata('total_customer_count', $totalCustomerCount);
    }

    private function importMultipleCustomers(array $customerObjects): void
    {
        $inserts = [];

        foreach ($customerObjects as $customerObject) {
            $stripeCustomer = StripeCustomer::constructFrom(json_decode($customerObject->data, true));

            $inserts[] = [
                'organization_id' => $this->billing->organization_id,
                'billing_provider_id' => $this->billing->id,
                'reference_id' => $customerObject->reference_id,
                'reference_created_at' => $customerObject->reference_created_at,
                'email' => $stripeCustomer->email,
                'name' => $stripeCustomer->name,
            ];
        }

        DB::table('account_customers')
            ->insertOrIgnore($inserts);

        DB::table('twins')
            ->whereIn('reference_id', array_column($customerObjects, 'reference_id'))
            ->update([
                'linkable_id' => DB::raw('(
                    SELECT id FROM account_customers
                    WHERE reference_id = twins.reference_id
                    AND twins.connector_id = '.$this->billing->id.'
                    AND account_customers.billing_provider_id = '.$this->billing->id.'
                    ORDER BY id
                    LIMIT 1
                )'),
                'linkable_type' => 'customer',
            ]);
    }
}
