<?php

use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\SearchConsoleController;
use App\Http\Controllers\SemrushController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('search-console', [SearchConsoleController::class, 'index'])->name('search-console');
    Route::get('semrush', [SemrushController::class, 'index'])->name('semrush');
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

    Route::middleware('throttle:60,1')->prefix('api/semrush')->name('semrush.')->group(function () {
        Route::get('balance', [SemrushController::class, 'apiBalance'])->name('balance');
        Route::get('domain-overview', [SemrushController::class, 'domainOverview'])->name('domain-overview');
        Route::get('organic-keywords', [SemrushController::class, 'organicKeywords'])->name('organic-keywords');
        Route::get('competitors', [SemrushController::class, 'competitors'])->name('competitors');
        Route::get('backlinks', [SemrushController::class, 'backlinks'])->name('backlinks');
        Route::get('keyword-overview', [SemrushController::class, 'keywordOverview'])->name('keyword-overview');
    });
});

require __DIR__.'/settings.php';
