<?php

namespace App\Jobs;

use App\Models\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnsureClientsHaveApiKeys implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $clients = Client::query()
            ->whereDoesntHave('tokens')
            ->get();

        /* @var Client $client */
        foreach ($clients as $client) {
            $client->createToken('api');
        }

        logger()->info(logname(), [
            'updated' => count($clients),
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
    }
}
