<?php

use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\Desktop\DesktopApiTokenController;
use App\Http\Controllers\Desktop\DesktopFavoriteController;
use App\Http\Controllers\Desktop\DesktopItemController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Public routes - accessible without authentication
Route::get('/items', [ItemController::class, 'index'])->name('items.index');
Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');
Route::get('/items/{item}/calculate-recursive', [ItemController::class, 'calculateRecursiveCost'])->name('items.calculate-recursive');

// Server selection - accessible without authentication
Route::post('/server/select', [ServerController::class, 'select'])->name('server.select');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    // Desktop-only native workspace API (used only by the desktop interface mode).
    Route::prefix('desktop/api')->name('desktop.api.')->group(function () {
        Route::get('/items', [DesktopItemController::class, 'index'])->name('items.index');
        Route::get('/items/{item}', [DesktopItemController::class, 'show'])->name('items.show');
        Route::get('/favorites', [DesktopFavoriteController::class, 'index'])->name('favorites.index');
        Route::post('/favorites/{item}', [DesktopFavoriteController::class, 'store'])->name('favorites.store');
        Route::delete('/favorites/{item}', [DesktopFavoriteController::class, 'destroy'])->name('favorites.destroy');
        Route::get('/api-tokens', [DesktopApiTokenController::class, 'index'])->name('api-tokens.index');
        Route::post('/api-tokens', [DesktopApiTokenController::class, 'store'])->name('api-tokens.store');
        Route::delete('/api-tokens/{token}', [DesktopApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
    });

    // Calculator routes
    Route::get('/calculator', [CalculatorController::class, 'index'])->name('calculator.index');
    Route::get('/calculator/recipe/{recipe}', [CalculatorController::class, 'show'])->name('calculator.show');

    // Price management routes
    Route::post('/prices', [PriceController::class, 'store'])->name('prices.store');
    Route::post('/prices/bulk', [PriceController::class, 'bulkUpdate'])->name('prices.bulk');
    Route::put('/prices/item-preference', [PriceController::class, 'updateItemPreference'])->name('prices.item-preference');

    // Price reporting routes
    Route::post('/prices/{itemPrice}/report', [\App\Http\Controllers\PriceReportController::class, 'store'])->name('prices.report');

    // Favorites routes
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites/{item}/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::delete('/favorites/{item}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');

    // API Token routes
    Route::get('/api-tokens', [ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('/api-tokens', [ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

    // Moderation routes (for admins/moderators)
    Route::middleware(['can:moderate'])->group(function () {
        Route::get('/moderation', [ModerationController::class, 'index'])->name('moderation.index');
        Route::post('/moderation/{itemPrice}/approve', [ModerationController::class, 'approve'])->name('moderation.approve');
        Route::post('/moderation/{itemPrice}/reject', [ModerationController::class, 'reject'])->name('moderation.reject');

        // Price reports moderation
        Route::get('/moderation/reports', [\App\Http\Controllers\PriceReportController::class, 'index'])->name('moderation.reports');
        Route::post('/moderation/reports/{report}/approve', [\App\Http\Controllers\PriceReportController::class, 'approve'])->name('moderation.reports.approve');
        Route::post('/moderation/reports/{report}/dismiss', [\App\Http\Controllers\PriceReportController::class, 'dismiss'])->name('moderation.reports.dismiss');

        // API Monitoring
        Route::get('/admin/api-monitoring', [\App\Http\Controllers\Admin\ApiMonitoringController::class, 'index'])->name('admin.api-monitoring');

    });

    // Admin-only routes
    Route::middleware(['can:admin'])->group(function () {
        Route::get('/admin/commands', [\App\Http\Controllers\Admin\AdminCommandController::class, 'index'])->name('admin.commands');
        Route::post('/admin/commands/import-recipes', [\App\Http\Controllers\Admin\AdminCommandController::class, 'importRecipes'])->name('admin.commands.import-recipes');
        Route::get('/admin/commands/import-recipes/status', [\App\Http\Controllers\Admin\AdminCommandController::class, 'importStatus'])->name('admin.commands.import-recipes.status');
    });
});
