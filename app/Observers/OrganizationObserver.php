<?php

namespace App\Observers;

use App\Models\Catalog\FeatureSet;
use App\Models\Client;
use App\Models\Convert\Theme;
use App\Models\Management\Organization;

class OrganizationObserver
{
    /**
     * Handle the Organization "created" event.
     */
    public function created(Organization $organization): void
    {
        if (! $organization->themes()->exists()) {
            $organization->themes()->save(Theme::newWithDefaults());
        }

        $organization->timezone = 'UTC';
        $organization->save();

        if (! $organization->clients()->where('environment', 'live')->exists()) {
            $client = new Client;
            $client->type = 'web';
            $client->name = 'Live Web Client';
            $client->environment = 'live';
            $client->regenerateSecret();
            $organization->clients()->save($client);
        }

        if (! $organization->clients()->where('environment', 'test')->exists()) {
            $client = new Client;
            $client->type = 'web';
            $client->name = 'Test Web Client';
            $client->environment = 'test';
            $client->regenerateSecret();
            $organization->clients()->save($client);
        }

        $defaultFeatureSet = new FeatureSet;
        $defaultFeatureSet->key = 'default';
        $defaultFeatureSet->name = 'Default Feature Set';
        $defaultFeatureSet->description = 'All features available to the organization';
        $defaultFeatureSet->released_at = now();
        $organization->featureSets()->save($defaultFeatureSet);
    }

    /**
     * Handle the Organization "updated" event.
     */
    public function updated(Organization $organization): void
    {
        //
    }

    /**
     * Handle the Organization "deleted" event.
     */
    public function deleted(Organization $organization): void
    {
        //
    }

    /**
     * Handle the Organization "restored" event.
     */
    public function restored(Organization $organization): void
    {
        //
    }

    /**
     * Handle the Organization "force deleted" event.
     */
    public function forceDeleted(Organization $organization): void
    {
        //
    }
}
