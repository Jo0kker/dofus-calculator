<?php

use App\Http\Controllers\Api\ItemApiController;
use App\Http\Controllers\Api\PriceApiController;
use App\Http\Controllers\Api\ServerApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Apply JSON response and tracking middleware to all API routes
Route::middleware(['force.json', 'track.api'])->group(function () {
    // Public routes (no authentication required)
    Route::get('/servers', [ServerApiController::class, 'index']);
    Route::get('/servers/{server}', [ServerApiController::class, 'show']);
    Route::get('/items', [ItemApiController::class, 'index']);
    Route::get('/items/{item}', [ItemApiController::class, 'show']);

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });

    // Write operations (requires authentication + write permission)
    Route::middleware(['auth:sanctum', 'abilities:write'])->group(function () {
        Route::post('/prices', [PriceApiController::class, 'store']);
        Route::post('/prices/bulk', [PriceApiController::class, 'bulkUpdate']);
    });
});
