<?php

namespace App\Jobs;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateClientExclusionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Client $client)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->client->exclusion_rules)) {
            return;
        }

//        PaywallSession::query()
//            ->join('account_customers', 'convert_paywall_sessions.customer_id', '=', 'account_customers.id')
//            ->join('stats_collectors', 'convert_paywall_sessions.collector_id', '=', 'stats_collectors.id')
//            ->where(function ($query) {
//                $this->buildExclusionQuery($query);
//            })
//            ->where('stats_collectors.client_id', $this->client->id)
//            ->where('is_excluded', false)
//            ->update([
//                'is_excluded' => true,
//                'exclusion_reason' => 'client_exclusion'
//            ]);
    }

    function buildExclusionQuery($query)
    {
        foreach ($this->client->exclusion_rules as $rule) {
            if (Arr::get($rule, 'rule') === 'emailDomainIs') {
                $query->where('account_customers.email', 'like', '%' . $rule['value'] . '%');
            }
        }

        return $query;
    }
}
