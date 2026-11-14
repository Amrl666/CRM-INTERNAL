<?php

use App\Http\Controllers\Api\EvolutionWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/webhooks/evolution', EvolutionWebhookController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.evolution');
