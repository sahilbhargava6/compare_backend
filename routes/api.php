<?php

use App\Http\Controllers\ProductIngestionController;
use App\Http\Controllers\SkinAnalysisController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('skin')->group(function () {
    Route::post('/analyze', [SkinAnalysisController::class, 'analyze'])->name('skin.analyze');
});

Route::prefix('products')->group(function () {
    Route::post('/ingest', [ProductIngestionController::class, 'ingest'])->name('products.ingest');
    Route::get('/{id}', [ProductIngestionController::class, 'show'])->name('products.show');
});

Route::prefix('watchlist')->group(function () {
    Route::get('/', [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::post('/', [WatchlistController::class, 'store'])->name('watchlist.store');
    Route::put('/{id}', [WatchlistController::class, 'update'])->name('watchlist.update');
    Route::delete('/{id}', [WatchlistController::class, 'destroy'])->name('watchlist.destroy');
});

Route::prefix('users')->group(function () {
    Route::get('/{id}/profile', [UserProfileController::class, 'show'])->name('users.profile.show');
    Route::put('/{id}/profile', [UserProfileController::class, 'update'])->name('users.profile.update');
});
