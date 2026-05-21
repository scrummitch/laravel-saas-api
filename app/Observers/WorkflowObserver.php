<?php

namespace App\Observers;

use App\Models\Convert\Flow;
use App\Models\Management\Organization;
use App\Models\Publish\Rollout;

class WorkflowObserver
{
    /**
     * Handle the Workflow "created" event.
     */
    public function created(Flow $workflow): void
    {
        if ($workflow->rollouts()->exists()) {
            return;
        }

        /** @var Organization */
        $org = $workflow->organization;

        $clients = $org->clients;

        foreach ($clients as $client) {
            $rollout = new Rollout;
            $rollout->organization()->associate($org);
            $rollout->publishable()->associate($workflow);
            $rollout->client()->associate($client);
            $rollout->rules = [
                [
                    'type' => 'percentage',
                    'value' => $client->environment === 'test' ? 100 : 0,
                ],
            ];
            $rollout->is_active = $client->environment === 'test';
            $rollout->save();
        }

        // y no ulid?

        //        $paywall = new Paywall();
        //        $paywall->name = $workflow->name. ' Paywall';
        //        $paywall->intent = 'upgrade';
        //        $paywall->mode = 'setup';
        //        $paywall->type = 'modal';
        //        $paywall->conditions = [];
        //        $paywall->checkout_config = [];
        //        $paywall->settings = [];
        //        $paywall->scheme()->associate($org->schemes()->latest('generated_at')->first());
        //        $paywall->workflow()->associate($workflow);
        //        $paywall->organization()->associate($org);
        //        $paywall->save();

    }

    /**
     * Handle the Workflow "updated" event.
     */
    public function updated(Flow $workflow): void {}

    /**
     * Handle the Workflow "deleted" event.
     */
    public function deleted(Flow $workflow): void
    {
        //
    }

    /**
     * Handle the Workflow "restored" event.
     */
    public function restored(Flow $workflow): void
    {
        //
    }

    /**
     * Handle the Workflow "force deleted" event.
     */
    public function forceDeleted(Flow $workflow): void
    {
        //
    }
}
