<?php

namespace App\Policies;

use App\Models\Pricing\Scheme;
use App\Models\User;

class SchemePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Scheme $scheme): bool
    {
        return $user->currentOrganization->id === $scheme->organization_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Scheme $scheme): bool
    {
        return $this->view($user, $scheme);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Scheme $scheme): bool
    {
        return $this->view($user, $scheme);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Scheme $scheme): bool
    {
        return $this->view($user, $scheme);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Scheme $scheme): bool
    {
        return false;
    }
}
