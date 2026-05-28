<?php

use App\Http\Controllers\Hooks\ProcessStripeHookController;
use Illuminate\Support\Facades\Route;

Route::post('stripe', ProcessStripeHookController::class)->name('stripe');
