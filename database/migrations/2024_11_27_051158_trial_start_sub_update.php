<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Stripe\Subscription as StripeSubscription;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        DB::table('mgmt_organizations')
            ->update(['timezone' => '+00:00']);

        DB::table('billing_subscriptions')
            ->join('twins', 'billing_subscriptions.twin_id', '=', 'twins.id')
            ->select('billing_subscriptions.id', 'twins.data')
            ->whereNotNull('billing_subscriptions.twin_id')
            ->orderBy('billing_subscriptions.id')
            ->chunk(1000, function ($rows) {
                $updates = [];

                foreach ($rows as $row) {
                    // Decode the JSON data from twins
                    $twin = \Stripe\Subscription::constructFrom(json_decode($row->data, true));

                    if (!$twin) {
                        continue;
                    }

                    if ($twin->start_date) {
                        $updates[] = [
                            'id' => $row->id,
                            'start_at' => $this->determineStartAt($twin),
                        ];
                    }
                }

                // Perform a batch update
                foreach (array_chunk($updates, 500) as $batch) {
                    foreach ($batch as $update) {
                        DB::table('billing_subscriptions')
                            ->where('id', $update['id'])
                            ->update(['start_at' => $update['start_at']]);
                    }
                }
            });
    }

    protected function determineStartAt(StripeSubscription $subscription): Carbon
    {
        if ($subscription->billing_cycle_anchor) {
            return Carbon::createFromTimestamp($subscription->billing_cycle_anchor);
        }

        $now = Carbon::now();

        if (!empty($subscription->trial_end) && $now->lt(Carbon::createFromTimestampUTC($subscription->trial_end))) {
            return Carbon::createFromTimestampUTC($subscription->trial_end);
        }

        return Carbon::createFromTimestampUTC($subscription->start_date);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
