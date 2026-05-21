<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\Usage\UsageEvent;
use App\Models\User;

class UsageEventPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(Client $client, UsageEvent $usageEvent): bool
    {
        return $client->organization->id === $usageEvent->organization_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(Client $client, UsageEvent $usageEvent): bool
    {
        return $this->view($client, $usageEvent);
    }
}
