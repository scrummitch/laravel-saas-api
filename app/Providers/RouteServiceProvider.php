<?php

namespace App\Providers;

use App\Database\Model;
use App\Http\Controllers\Web\HealthCheckController;
use App\Models\Catalog\ProductFamily;
use App\Models\Catalog\ProductFeature;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // Tests routinely hit the api many times in a single test method;
            // throttling them produces false-positive 429s that hide real bugs.
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });

        $this->routes(function () {
            Route::get('/health', HealthCheckController::class);

            Route::middleware(['hooks'])
                ->prefix('hooks')
                ->as('hooks/')
                ->group(base_path('routes/hooks.php'));

            Route::middleware(['api', 'auth:sanctum'])
                ->prefix('v1')
                ->as('api/')
                ->group(base_path('routes/api.php'));

            Route::middleware(['client'])
                ->withoutMiddleware([
                    HandleCors::class,
                ])
                ->prefix('client')
                ->as('client/')
                ->group(base_path('routes/client.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
