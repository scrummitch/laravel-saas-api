<?php

namespace App\Policies;

use App\Models\Catalog\Inclusion;
use App\Models\User;

class InclusionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentOrganization !== null;
    }

    public function view(User $user, Inclusion $inclusion): bool
    {
        return $this->sameOrganization($user, $inclusion);
    }

    public function create(User $user): bool
    {
        return $user->currentOrganization !== null;
    }

    public function update(User $user, Inclusion $inclusion): bool
    {
        return $this->sameOrganization($user, $inclusion);
    }

    public function delete(User $user, Inclusion $inclusion): bool
    {
        return $this->sameOrganization($user, $inclusion);
    }

    public function restore(User $user, Inclusion $inclusion): bool
    {
        return $this->sameOrganization($user, $inclusion);
    }

    public function forceDelete(User $user, Inclusion $inclusion): bool
    {
        return false;
    }

    /**
     * Inclusion has no own organization_id — it inherits via its parent plan.
     */
    private function sameOrganization(User $user, Inclusion $inclusion): bool
    {
        $org = $user->currentOrganization;

        return $org !== null && (int) $org->id === (int) $inclusion->plan?->organization_id;
    }
}
