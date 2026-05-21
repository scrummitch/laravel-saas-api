<?php

namespace App\Policies;

use App\Models\Catalog\FeatureSet;
use App\Models\User;

class FeatureSetPolicy
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
    public function view(User $user, FeatureSet $featureSet): bool
    {
        return $user->currentOrganization->id === $featureSet->organization_id;
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
    public function update(User $user, FeatureSet $featureSet): bool
    {
        return $this->view($user, $featureSet);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FeatureSet $featureSet): bool
    {
        return $this->view($user, $featureSet);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, FeatureSet $featureSet): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, FeatureSet $featureSet): bool
    {
        //
    }
}
