<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Handle favicon requests to prevent 302 redirects
Route::get('/favicon.ico', function () {
    return response()->file(public_path('favicon.ico'));
});


Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('map', function () {
        return Inertia::render('map');
    })->name('map');

    Route::get('performance', function () {
        return Inertia::render('PerformanceDashboard');
    })->name('performance');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

// API 路由
Route::prefix('api')->middleware(['auth', 'verified'])->group(function () {
    // AI 分析 API
    Route::post('/ai/analyze', [App\Http\Controllers\AIController::class, 'analyze']);
    Route::post('/ai/detect-anomalies', [App\Http\Controllers\AIController::class, 'detectAnomalies']);
    Route::post('/ai/predict-prices', [App\Http\Controllers\AIController::class, 'predictPrices']);
    Route::post('/ai/generate-heatmap', [App\Http\Controllers\AIController::class, 'generateHeatmap']);
    Route::get('/ai/status', [App\Http\Controllers\AIController::class, 'status']);
    
    // 地圖 API (待 Claude Code 開發)
    Route::get('/map/rentals', [App\Http\Controllers\MapController::class, 'getRentals']);
    Route::get('/map/heatmap', [App\Http\Controllers\MapController::class, 'getHeatmap']);
    Route::get('/map/clusters', [App\Http\Controllers\MapController::class, 'getClusters']);
});
