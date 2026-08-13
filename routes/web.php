<?php

use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\SearchConsoleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('search-console', 'SearchConsole/Index')->name('search-console');
});

Route::middleware('auth')->group(function () {
    Route::get('google/connect', [GoogleAuthController::class, 'connect'])->name('google.connect');
    Route::get('google/callback', [GoogleAuthController::class, 'callback'])->name('google.callback');
    Route::post('google/disconnect', [GoogleAuthController::class, 'disconnect'])->name('google.disconnect');

    Route::middleware('throttle:60,1')->prefix('api/search-console')->name('search-console.')->group(function () {
        Route::get('properties', [SearchConsoleController::class, 'properties'])->name('properties');
        Route::get('analytics', [SearchConsoleController::class, 'analytics'])->name('analytics');
        Route::get('queries', [SearchConsoleController::class, 'queries'])->name('queries');
        Route::get('pages', [SearchConsoleController::class, 'pages'])->name('pages');
        Route::get('countries', [SearchConsoleController::class, 'countries'])->name('countries');
        Route::get('devices', [SearchConsoleController::class, 'devices'])->name('devices');
        Route::get('search-appearance', [SearchConsoleController::class, 'searchAppearance'])->name('search-appearance');
    });
});

require __DIR__.'/settings.php';
