<?php

namespace App\Policies;

use App\Models\Billing\Charge;
use App\Models\User;

class ChargePolicy
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
    public function view(User $user, Charge $charge): bool
    {
        return $user->currentOrganization->id === $charge->organization_id;
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
    public function update(User $user, Charge $charge): bool
    {
        return $this->view($user, $charge);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Charge $charge): bool
    {
        return $this->view($user, $charge);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Charge $charge): bool
    {
        return $this->view($user, $charge);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Charge $charge): bool
    {
        return false;
    }
}
