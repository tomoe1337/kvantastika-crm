<?php

use App\Http\Controllers\Api\DealController;
use App\Http\Controllers\Api\RefreshDealController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/deals', [DealController::class, 'index']);
Route::post('/deals', [DealController::class, 'store']);
Route::get('/deals/{deal}', [DealController::class, 'show']);
Route::patch('/deals/{deal}', [DealController::class, 'update']);
Route::post('/deals/{deal}/refresh', [RefreshDealController::class, 'store']);
Route::post('/webhooks/deals', [WebhookController::class, 'store']);
