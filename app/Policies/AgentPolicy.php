<?php

namespace App\Policies;

use App\Models\Account\Agent;
use App\Models\User;

class AgentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentOrganization !== null;
    }

    public function view(User $user, Agent $agent): bool
    {
        return $this->sameOrganization($user, $agent);
    }

    public function create(User $user): bool
    {
        return $user->currentOrganization !== null;
    }

    public function update(User $user, Agent $agent): bool
    {
        return $this->sameOrganization($user, $agent);
    }

    public function delete(User $user, Agent $agent): bool
    {
        return $this->sameOrganization($user, $agent);
    }

    public function restore(User $user, Agent $agent): bool
    {
        return $this->sameOrganization($user, $agent);
    }

    public function forceDelete(User $user, Agent $agent): bool
    {
        return $this->sameOrganization($user, $agent);
    }

    private function sameOrganization(User $user, Agent $agent): bool
    {
        return $user->currentOrganization?->id === $agent->organization_id;
    }
}
