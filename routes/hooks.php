<?php

use App\Http\Controllers\Hooks\ProcessStripeHookController;
use Illuminate\Support\Facades\Route;

// https://me.flindev.com/hooks/stripe
// todo: install into connect!

Route::any('stripe', ProcessStripeHookController::class);
