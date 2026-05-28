<?php

namespace App\Policies;

use App\Models\Billing\Charge;
use App\Models\User;
use App\Policies\Concerns\TenantScoped;

class ChargePolicy
{
    use TenantScoped;

    public function forceDelete(User $user, Charge $charge): bool
    {
        return false;
    }
}
