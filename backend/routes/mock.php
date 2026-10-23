<?php

use App\Http\Controllers\MockApiController;
use Illuminate\Support\Facades\Route;

Route::get('/deals/{externalId}', [MockApiController::class, 'show']);
Route::put('/deals/{externalId}', [MockApiController::class, 'store']);
Route::post('/deals/{externalId}/reset', [MockApiController::class, 'reset']);
