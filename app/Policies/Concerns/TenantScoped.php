<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Default authorization for any model that carries an `organization_id`.
 *
 * Each method allows the action when the user has a current organization
 * and (for model-bound checks) the model belongs to that organization.
 * Override individual methods in a policy to add role/permission rules.
 */
trait TenantScoped
{
    public function viewAny(User $user): bool
    {
        return $user->currentOrganization !== null;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->sameOrganization($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->currentOrganization !== null;
    }

    public function update(User $user, Model $model): bool
    {
        return $this->sameOrganization($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->sameOrganization($user, $model);
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->sameOrganization($user, $model);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->sameOrganization($user, $model);
    }

    protected function sameOrganization(User $user, Model $model): bool
    {
        $org = $user->currentOrganization;

        return $org !== null && (int) $org->id === (int) $model->getAttribute('organization_id');
    }
}
