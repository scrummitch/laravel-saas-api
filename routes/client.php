<?php

use App\Http\Controllers\API\Actions\ApplyCheckoutMutationAction;
use App\Http\Controllers\API\Actions\UpdateClientCheckoutAction;
use App\Http\Controllers\Client\CreateUsageEventController;
use App\Http\Controllers\Client\GetClientSessionAction;
use App\Http\Controllers\Client\StoreClientEventAction;
use App\Http\Controllers\Client\StoreClientPaywallEventsAction;
use App\Http\Controllers\Client\StoreSetupIntentAction;
use App\Http\Controllers\Client\StoreTransactionAction;
use App\Http\Controllers\Clients\ClientPaywallController;
use App\Http\Resources\Client\CheckoutClientResource;
use Illuminate\Support\Facades\Route;

// start an SDK session
Route::any('/session', GetClientSessionAction::class)->name('sessions');

// sdk events
Route::post('/events', StoreClientEventAction::class)->name('events');

// Set up a new payment method
Route::post('/bsp/setup-intents', StoreSetupIntentAction::class);

// Store a transaction
Route::post('/store/transactions', StoreTransactionAction::class)->name('transactions.create');

// usage events
Route::post('/usage/events', CreateUsageEventController::class)->name('usage.events');

// elements/{id} view for element

Route::group([
    'prefix' => '/checkouts',
    'as' => 'checkouts.',
], function () {
    Route::get('/{purchase}', function (\App\Models\Store\Purchase $purchase) {
        return new CheckoutClientResource($purchase);
    });
    Route::patch('/{purchase}', UpdateClientCheckoutAction::class);
    Route::post('/{purchase}/mutations', ApplyCheckoutMutationAction::class);
});

/* @deprecated  */
Route::get('/paywalls/{paywall}', ClientPaywallController::class)->name('paywall');

/* @deprecated  */
Route::post('/paywall-events', StoreClientPaywallEventsAction::class)->name('paywall.events.store');


Route::fallback(function () {
    return response()->json([
        'message' => 'Not Found',
    ], 404);
})->name('fallback');
