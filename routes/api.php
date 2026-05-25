<?php

use App\Http\Controllers\API\AccountSignalsController;
use App\Http\Controllers\API\Actions\CreateOperationAttemptController;
use App\Http\Controllers\API\AgentsController;
use App\Http\Controllers\API\BillingProvidersController;
use App\Http\Controllers\API\ChargesController;
use App\Http\Controllers\API\ClientsController;
use App\Http\Controllers\API\CreateIntegrationAuthorizationController;
use App\Http\Controllers\API\CreateSanctumTokenController;
use App\Http\Controllers\API\CustomersController;
use App\Http\Controllers\API\CustomerUsageController;
use App\Http\Controllers\API\Dashboard\DashboardActivitiesController;
use App\Http\Controllers\API\Dashboard\DashboardActivityController;
use App\Http\Controllers\API\Dashboard\DashboardConversionsController;
use App\Http\Controllers\API\Dashboard\DashboardRevenueController;
use App\Http\Controllers\API\Dashboard\DashboardStatsAction;
use App\Http\Controllers\API\ElementsController;
use App\Http\Controllers\API\FeaturesController;
use App\Http\Controllers\API\FeatureSetsController;
use App\Http\Controllers\API\FlowActivitiesController;
use App\Http\Controllers\API\FlowScenariosController;
use App\Http\Controllers\API\FlowsController;
use App\Http\Controllers\API\Intel\ScenariosController;
use App\Http\Controllers\API\ListIntegrationsController;
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
use Illuminate\Support\Facades\Route;

Route::get('/users/me', [UserController::class, 'show'])->name('users.me');
Route::get('/user', [UserController::class, 'show']);

Route::post('/sanctum/token', CreateSanctumTokenController::class)->name('sanctum.token');

Route::prefix('/dashboard')->name('dashboard.')->group(function () {
    Route::get('/stats', DashboardStatsAction::class)->name('stats');
    Route::get('/activity', DashboardActivityController::class)->name('activity');
    Route::get('/revenue', DashboardRevenueController::class)->name('revenue');
    Route::get('/conversions', DashboardConversionsController::class)->name('conversions');
    Route::get('/activities', DashboardActivitiesController::class)->name('activities');
});

Route::prefix('/users')->name('users.')->group(function () {
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

Route::prefix('/account')->name('account.')->group(function () {
    Route::get('/signals', [AccountSignalsController::class, 'index'])->name('signals.index');
    Route::get('/agents', [AgentsController::class, 'index'])->name('agents.index');
    Route::delete('/agents/{agent}', [AgentsController::class, 'destroy'])->name('agents.destroy');
});

Route::apiResource('/customers', CustomersController::class);
Route::get('customers/{customer}/usage', [CustomerUsageController::class, 'current'])->name('customers.usage');
Route::get('customers/{customer}/usage/current', [CustomerUsageController::class, 'current'])->name('customers.usage.current');
Route::get('customers/{customer}/usage/past', [CustomerUsageController::class, 'past'])->name('customers.usage.past');

Route::prefix('/mgmt')->name('mgmt.')->group(function () {
    Route::post('operations/{operation}/attempts', CreateOperationAttemptController::class);
    Route::apiResource('organizations.memberships', OrganizationMembershipsController::class);
    Route::apiResource('organizations', OrganizationController::class);
});

Route::prefix('/usage')->group(function () {
    Route::apiResource('metrics', MetricsController::class);
    Route::apiResource('/events', UsageEventsController::class);
});

Route::prefix('/billing')->group(function () {
    Route::apiResource('charges', ChargesController::class);
    Route::apiResource('providers', BillingProvidersController::class);
    Route::post('providers/{provider}/operations', [BillingProvidersController::class, 'storeOperation'])->name('operations.store');
});

Route::prefix('/catalog')->name('catalog.')->group(function () {
    Route::apiResource('feature_sets', FeatureSetsController::class);
    Route::apiResource('features', FeaturesController::class);
    Route::apiResource('products', ProductsController::class);
    Route::apiResource('product_families', ProductFamiliesController::class)->parameters([
        'product_families' => 'product_family',
    ]);
    Route::apiResource('products.features', ProductFeaturesController::class);
    Route::apiResource('products.plans', ProductPlansController::class);
});

Route::prefix('/pricing')->name('pricing.')->group(function () {
    Route::apiResource('plans', PlansController::class);
    Route::apiResource('plans.inclusions', PlanInclusionsController::class);
    Route::apiResource('packages', PackagesController::class);
    Route::apiResource('schemes', SchemesController::class);
    Route::apiResource('schemes.plans', SchemePlansController::class);
    Route::put('schemes/{scheme}/plans', [SchemePlansController::class, 'replace'])->name('schemes.plans.replace');
});

Route::prefix('/intel')->name('intel.')->group(function () {
    Route::apiResource('scenarios', ScenariosController::class);
    Route::apiResource('flows', FlowsController::class);
    Route::apiResource('flows.activities', FlowActivitiesController::class);
    Route::apiResource('flows.scenarios', FlowScenariosController::class);

    Route::get('flows/{flow}/conversions', [FlowsController::class, 'conversionsIndex']);
    Route::get('flows/{flow}/results', [FlowsController::class, 'resultsIndex']);
});

Route::prefix('/convert')->name('convert.')->group(function () {
    Route::apiResource('elements', ElementsController::class);
    Route::apiResource('themes', ThemesController::class);
    Route::apiResource('workflows', FlowsController::class);
    Route::apiResource('workflows.rollouts', WorkflowRolloutsController::class);
    Route::get('workflows/{flow}/sessions', [FlowsController::class, 'sessionsIndex']);
    Route::get('workflows/{flow}/events', [FlowsController::class, 'eventsIndex']);
    Route::post('workflows/{flow}/agents', [FlowsController::class, 'storeSandboxAgent']);
});

Route::prefix('/integrations')->name('integrations.')->group(function () {
    Route::get('', ListIntegrationsController::class)->name('index');
    Route::post('/{provider}/authorizations', CreateIntegrationAuthorizationController::class);
});

Route::post('/media/assets', StoreAssetControllerAction::class)->name('media.assets.store');
Route::put('/media/{key}/uploads', UploadAssetControllerAction::class)
    ->name('media.uploads.store')
    ->withoutMiddleware([VerifyCsrfToken::class]);
