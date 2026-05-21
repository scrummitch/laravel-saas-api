<?php

namespace App\Providers;

use App\Client\PersonalAccessToken;
use App\Models\Billing\Charge;
use App\Models\Client;
use App\Models\Usage\UsageEvent;
use App\Policies\ChargePolicy;
use App\Policies\ClientPolicy;
use App\Policies\UsageEventPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Client::class => ClientPolicy::class,
        Charge::class => ChargePolicy::class,
        UsageEvent::class => UsageEventPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }
}
