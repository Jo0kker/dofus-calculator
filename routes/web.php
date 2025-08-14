<?php

use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\ServerController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('calculator.index');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('calculator.index');
    })->name('dashboard');
    
    // Calculator routes
    Route::get('/calculator', [CalculatorController::class, 'index'])->name('calculator.index');
    Route::get('/calculator/recipe/{recipe}', [CalculatorController::class, 'show'])->name('calculator.show');
    
    // Items routes
    Route::get('/items', [ItemController::class, 'index'])->name('items.index');
    Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');
    
    // Price management routes
    Route::post('/prices', [PriceController::class, 'store'])->name('prices.store');
    Route::post('/prices/bulk', [PriceController::class, 'bulkUpdate'])->name('prices.bulk');
    Route::post('/prices/{itemPrice}/report', [PriceController::class, 'report'])->name('prices.report');
    
    // Favorites routes
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites/{item}/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    
    // Server selection
    Route::post('/server/select', [ServerController::class, 'select'])->name('server.select');
    
    // Moderation routes (for admins/moderators)
    Route::middleware(['can:moderate'])->group(function () {
        Route::get('/moderation', [ModerationController::class, 'index'])->name('moderation.index');
        Route::post('/moderation/{itemPrice}/approve', [ModerationController::class, 'approve'])->name('moderation.approve');
        Route::post('/moderation/{itemPrice}/reject', [ModerationController::class, 'reject'])->name('moderation.reject');
    });
});

// Public routes that don't require authentication
Route::get('/calculator-public', [CalculatorController::class, 'index'])->name('calculator.public');
Route::get('/items-public', [ItemController::class, 'index'])->name('items.public');
