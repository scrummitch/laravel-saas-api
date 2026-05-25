<?php

namespace App\Policies;

use App\Models\Pricing\Scheme;
use App\Models\User;
use App\Policies\Concerns\TenantScoped;

class SchemePolicy
{
    use TenantScoped;

    public function forceDelete(User $user, Scheme $scheme): bool
    {
        return false;
    }
}
