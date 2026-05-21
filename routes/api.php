<?php

use App\Http\Controllers\API\AccountSignalsController;
use App\Http\Controllers\API\Actions\CreateOperationAttemptController;
use App\Http\Controllers\API\BillingProvidersController;
use App\Http\Controllers\API\ChargesController;
use App\Http\Controllers\API\ClientsController;
use App\Http\Controllers\API\CreateIntegrationAuthorizationController;
use App\Http\Controllers\API\CustomersController;
use App\Http\Controllers\API\Dashboard\DashboardStatsAction;
use App\Http\Controllers\API\CustomerUsageController;
use App\Http\Controllers\API\ElementsController;
use App\Http\Controllers\API\FeaturesController;
use App\Http\Controllers\API\FeatureSetsController;
use App\Http\Controllers\API\FlowActivitiesController;
use App\Http\Controllers\API\FlowScenariosController;
use App\Http\Controllers\API\FlowsController;
use App\Http\Controllers\API\Intel\ScenariosController;
use App\Http\Controllers\API\Media\StoreAssetControllerAction;
use App\Http\Controllers\API\Media\UploadAssetControllerAction;
use App\Http\Controllers\API\MetricsController;
use App\Http\Controllers\API\OrganizationController;
use App\Http\Controllers\API\OrganizationMembershipsController;
use App\Http\Controllers\API\PackagesController;
use App\Http\Controllers\API\PlanInclusionsController;
use App\Http\Controllers\API\PlansController;
use App\Http\Controllers\API\ProductFamiliesController;
use App\Http\Controllers\API\ProductFeaturesController;
use App\Http\Controllers\API\ProductPlansController;
use App\Http\Controllers\API\ProductsController;
use App\Http\Controllers\API\SchemePlansController;
use App\Http\Controllers\API\SchemesController;
use App\Http\Controllers\API\ThemesController;
use App\Http\Controllers\API\UsageEventsController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\WorkflowRolloutsController;
use App\Http\Middleware\VerifyCsrfToken;
use App\Http\Resources\Api\ActivityApiResource;
use App\Http\Resources\Api\SignalApiResource;
use App\Http\Resources\MeResource;
use App\Models\Billing\BillingProvider;
use Illuminate\Http\Request;
use App\Models\Client;
use App\Services\Analytics\DashboardStatsService;
use App\Services\Analytics\StatDateRange;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get('/users/me', fn () => new MeResource(auth()->user()))->name('users.me');
Route::get('/user', function () {
    return new MeResource(auth()->user());
});

Route::post('/sanctum/token', function (Request $request) {
    return $request->user()->createToken($request->userAgent(), ['read:paywalls'])->plainTextToken;
})->name('sanctum.token');

Route::get('/dashboard/stats', DashboardStatsAction::class)->name('dashboard.stats');

Route::get('/dashboard/activity', function (Request $request) {
    $organization = $request->user()->currentOrganization;
    $client = Client::retrieve($request->get('client')) ?? $organization->liveClient();
    $stats = new DashboardStatsService(range: StatDateRange::last_30_days, client: $client);

    return $stats->getActivityTimeSeries();
})->name('dashboard.activity');

Route::get('/dashboard/revenue', function (Request $request) {
    $organization = $request->user()->currentOrganization;
    $client = Client::retrieve($request->get('client')) ?? $organization->liveClient();
    $stats = new DashboardStatsService(
        range: StatDateRange::tryFrom($request->get('range')) ?? StatDateRange::this_month,
        client: $client
    );

    return $stats->getRevenueTrend();
})->name('dashboard.revenue');

Route::get('/dashboard/conversions', function (Request $request) {
    $organization = $request->user()->currentOrganization;

    // signal associated?

    // change this to completed purchases
    $purchases = \App\Models\Store\Purchase::query()
        ->where('organization_id', $organization->id)
        ->where('current_state', 'completed')
        ->latest('created_at')
        ->with([
            'customer',
            'customer.twin',
            'customer.twin.connector',
        ])
        ->limit(10)
        ->get();

    return [
        'data' => $purchases
            ->map(function($activity) {
                return [
                    'id' => $activity->getRouteKey(),
                    'created_at' => $activity->created_at,
                    'customer_id' => $activity->customer_id,
                    'customer' => $activity->customer ? new \App\Http\Resources\Api\CustomerApiResource($activity->customer) : null,
                ];
            }),
    ];
})->name('dashboard.conversions');

Route::get('/dashboard/activities', function (Request $request) {
    $organization = $request->user()->currentOrganization;
    $client = Client::retrieve($request->get('client')) ?? $organization->liveClient();

    $query = \App\Models\Intelligence\Activity::query()
        ->where('client_id', $client->id)
        ->latest('id')
        ->with(['scenario', 'flow', 'actions', 'collector', 'customer', 'collector.agent', 'customer', 'customer.twin', 'customer.twin.connector'])
        ->withCount(['views']);

    return ActivityApiResource::collection($query->simplePaginate());
})->name('dashboard.activities');

Route::group([
    'prefix' => '/users',
    'as' => 'users.',
], function () {
    Route::get('/me', [UserController::class, 'show']);
    Route::patch('/me', [UserController::class, 'update']);
    Route::post('/me/organizations', [UserController::class, 'storeOrganization']);
    Route::get('', [UserController::class, 'show']);
    Route::put('', [UserController::class, 'update']);
    Route::post('/change-password', [UserController::class, 'changePassword']);
});

Route::apiResource('/clients', ClientsController::class);
Route::get('/clients/{client}/secret', [ClientsController::class, 'secret'])->name('clients.secret');
Route::apiResource('/organizations', OrganizationController::class);

Route::group(['prefix' => '/account'], function () {
    Route::get('/signals', [AccountSignalsController::class, 'index'])->name('account.signals.index');

    Route::get('/agents', function (Request $request) {
        $org = $request->user()->currentOrganization;

        $agents = \App\Models\Account\Agent::query()
            ->latest()
            ->with(['associations', 'associations.customer'])
            ->where('organization_id', $org->id);

        return $agents->simplePaginate();
    })->name('account.agents.index');

    Route::delete('/agents/{agent}', function (\App\Models\Account\Agent $agent, Request $request) {
        Gate::authorize('delete', $agent);

        $agent->deleteOrFail();

        return response()->noContent();
    })->name('account.agents.destroy');
});

Route::apiResource('/customers', CustomersController::class);
Route::get('customers/{customer}/usage', [CustomerUsageController::class, 'current'])->name('customers.usage');
Route::get('customers/{customer}/usage/current', [CustomerUsageController::class, 'current'])->name('customers.usage.current');
Route::get('customers/{customer}/usage/past', [CustomerUsageController::class, 'past'])->name('customers.usage.past');

Route::group([
    'prefix' => '/mgmt',
    'as' => 'mgmt.',
], function () {
    Route::post('operations/{operation}/attempts', CreateOperationAttemptController::class);
    Route::apiResource('organizations.memberships', OrganizationMembershipsController::class);
    Route::apiResource('organizations', OrganizationController::class);
});

Route::group([
    'prefix' => '/usage',
], function () {
    Route::apiResource('metrics', MetricsController::class);
    Route::apiResource('/events', UsageEventsController::class);
});

Route::group([
    'prefix' => '/billing',
], function () {
    Route::apiResource('charges', ChargesController::class);
    Route::apiResource('providers', BillingProvidersController::class);
    Route::post('providers/{provider}/operations', [BillingProvidersController::class, 'storeOperation'])->name('operations.store');
});

Route::group([
    'prefix' => '/catalog',
    'as' => 'catalog.',
], function () {
    Route::apiResource('feature_sets', FeatureSetsController::class);
    Route::apiResource('features', FeaturesController::class);
    Route::apiResource('products', ProductsController::class);
    Route::apiResource('product_families', ProductFamiliesController::class)->parameters([
        'product_families' => 'product_family',
    ]);
    Route::apiResource('products.features', ProductFeaturesController::class);
    Route::apiResource('products.plans', ProductPlansController::class);
});

Route::group([
    'prefix' => '/pricing',
    'as' => 'pricing.',
], function () {
    Route::apiResource('plans', PlansController::class);
    Route::apiResource('plans.inclusions', PlanInclusionsController::class);

    Route::apiResource('packages', PackagesController::class);
    Route::apiResource('schemes', SchemesController::class);
    Route::apiResource('schemes.plans', SchemePlansController::class);
    Route::put('schemes/{scheme}/plans', [SchemePlansController::class, 'replace'])->name('schemes.plans.replace');
});

Route::group([
    'prefix' => '/intel',
    'as' => 'intel.',
], function () {
    Route::apiResource('scenarios', ScenariosController::class);
    Route::apiResource('flows', FlowsController::class);
    Route::apiResource('flows.activities', FlowActivitiesController::class);
    Route::apiResource('flows.scenarios', FlowScenariosController::class);

    Route::get('flows/{flow}/conversions', [FlowsController::class, 'conversionsIndex']);
    Route::get('flows/{flow}/results', [FlowsController::class, 'resultsIndex']);
});

Route::group([
    'prefix' => '/convert',
    'as' => 'convert.',
], function () {
    Route::apiResource('elements', ElementsController::class);

    Route::apiResource('themes', ThemesController::class);
    Route::apiResource('workflows', FlowsController::class);
//    Route::apiResource('attributions', AttributionsController::class);
    Route::apiResource('workflows.rollouts', WorkflowRolloutsController::class);
    Route::get('workflows/{flow}/sessions', [FlowsController::class, 'sessionsIndex']);
    Route::get('workflows/{flow}/events', [FlowsController::class, 'eventsIndex']);
    Route::post('workflows/{flow}/agents', [FlowsController::class, 'storeSandboxAgent']);
});

Route::group([
    'prefix' => '/integrations',
    'as' => 'integrations.',
], function () {
    Route::get('', function () {
        return [
            'data' => [
                BillingProvider::integrationDescriptor('stripe'),
                BillingProvider::integrationDescriptor('stripe_test'),
            ],
        ];
    });

    Route::post('/{provider}/authorizations', CreateIntegrationAuthorizationController::class);
});

Route::post('/media/assets', StoreAssetControllerAction::class)->name('media.assets.store');
Route::put('/media/{key}/uploads', UploadAssetControllerAction::class)
    ->name('media.uploads.store')
    ->withoutMiddleware([VerifyCsrfToken::class]);
