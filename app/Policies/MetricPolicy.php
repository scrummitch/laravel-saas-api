<?php

namespace App\Policies;

use App\Models\Usage\Metric;
use App\Models\User;

class MetricPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return ! is_null($user->currentOrganization->id);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Metric $metric): bool
    {
        return $user->currentOrganization->id === $metric->organization_id;
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
    public function update(User $user, Metric $metric): bool
    {
        return $this->view($user, $metric);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Metric $metric): bool
    {
        return $this->view($user, $metric);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Metric $metric): bool
    {
        return $this->view($user, $metric);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Metric $metric): bool
    {
        return false;
    }
}
