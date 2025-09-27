<?php

use App\Http\Controllers\AIController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MarketAnalysisController;
use App\Http\Controllers\PerformanceDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// AI analysis API
Route::prefix('ai')->group(function () {
    Route::post('/analyze', [AIController::class, 'analyze']);
    Route::post('/detect-anomalies', [AIController::class, 'detectAnomalies']);
    Route::post('/predict-prices', [AIController::class, 'predictPrices']);
    Route::post('/generate-heatmap', [AIController::class, 'generateHeatmap']);
    Route::get('/status', [AIController::class, 'status']);
});

// Map data API
Route::prefix('map')->group(function () {
    Route::get('/rentals', [MapController::class, 'index']);
    Route::get('/heatmap', [MapController::class, 'heatmapData']);
    Route::get('/clusters', [MapController::class, 'clusters']);
    Route::get('/statistics', [MapController::class, 'statistics']);
    Route::get('/districts', [MapController::class, 'districts']);
    Route::get('/ai-heatmap', [MapController::class, 'aiHeatmap']);
    Route::get('/predict-prices', [MapController::class, 'predictPrices']);
    Route::get('/optimized-data', [MapController::class, 'optimizedData']);
    Route::post('/notify', [MapController::class, 'sendNotification']);
});

// Performance dashboard API
Route::prefix('dashboard')->group(function () {
    Route::get('/overview', [PerformanceDashboardController::class, 'getOverview']);
    Route::get('/performance', [PerformanceDashboardController::class, 'getPerformanceMetrics']);
    Route::get('/errors', [PerformanceDashboardController::class, 'getErrorLogs']);
    Route::get('/behaviors', [PerformanceDashboardController::class, 'getUserBehaviors']);
    Route::get('/session-analysis', [PerformanceDashboardController::class, 'getSessionAnalysis']);
    Route::post('/log-error', [PerformanceDashboardController::class, 'logError']);
    Route::post('/track-behavior', [PerformanceDashboardController::class, 'trackBehavior']);
    Route::post('/cleanup', [PerformanceDashboardController::class, 'cleanupOldData']);
    Route::get('/realtime-metrics', [PerformanceDashboardController::class, 'getRealtimeMetrics']);
    Route::get('/system-health', [PerformanceDashboardController::class, 'getSystemHealth']);
});

// Market analysis API
Route::prefix('analysis')->group(function () {
    Route::get('/overview', [MarketAnalysisController::class, 'overview']);
    Route::post('/report', [MarketAnalysisController::class, 'report']);
});

