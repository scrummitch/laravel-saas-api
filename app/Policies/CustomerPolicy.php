<?php

namespace App\Policies;

use App\Policies\Concerns\TenantScoped;

class CustomerPolicy
{
    use TenantScoped;
}
