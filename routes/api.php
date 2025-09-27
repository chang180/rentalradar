<?php

use App\Http\Controllers\AIController;
use App\Http\Controllers\MapController;
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

// AI 分析 API
Route::prefix('ai')->group(function () {
    Route::post('/analyze', [AIController::class, 'analyze']);
    Route::post('/detect-anomalies', [AIController::class, 'detectAnomalies']);
    Route::post('/predict-prices', [AIController::class, 'predictPrices']);
    Route::post('/generate-heatmap', [AIController::class, 'generateHeatmap']);
    Route::get('/status', [AIController::class, 'status']);
});

// 地圖 API
Route::prefix('map')->group(function () {
    Route::get('/rentals', [MapController::class, 'getRentals']);
    Route::get('/heatmap', [MapController::class, 'getHeatmap']);
    Route::get('/clusters', [MapController::class, 'getClusters']);
    Route::get('/statistics', [MapController::class, 'getStatistics']);
    Route::get('/districts', [MapController::class, 'getDistricts']);
    Route::get('/ai-heatmap', [MapController::class, 'getAIHeatmap']);
    Route::get('/predict-prices', [MapController::class, 'predictPrices']);
    Route::get('/optimized-data', [MapController::class, 'getOptimizedData']);
    Route::post('/notify', [MapController::class, 'sendNotification']);
});

// 效能監控儀表板 API
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