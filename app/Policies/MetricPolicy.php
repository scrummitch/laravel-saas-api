<?php

namespace App\Policies;

use App\Models\Usage\Metric;
use App\Models\User;
use App\Policies\Concerns\TenantScoped;

class MetricPolicy
{
    use TenantScoped;

    public function forceDelete(User $user, Metric $metric): bool
    {
        return false;
    }
}
