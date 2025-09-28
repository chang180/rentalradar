<?php

use App\Http\Controllers\AIController;
use App\Http\Controllers\AIPredictionController;
use App\Http\Controllers\AnomalyDetectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MapAIController;
use App\Http\Controllers\MapDataController;
use App\Http\Controllers\MapNotificationController;
use App\Http\Controllers\MarketAnalysisController;
use App\Http\Controllers\PerformanceDashboardController;
use App\Http\Controllers\PublicMapController;
use App\Http\Controllers\RecommendationController;
use App\Http\Controllers\RiskAssessmentController;
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

// Public map API
Route::prefix('public-map')->group(function () {
    Route::get('/availability', [PublicMapController::class, 'checkAvailability']);
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
    // 基本地圖資料路由 - 使用 MapDataController (帶快取)
    Route::get('/rentals', [MapDataController::class, 'index']);
    Route::get('/statistics', [MapDataController::class, 'statistics']);
    Route::get('/cities', [MapDataController::class, 'cities']);
    Route::get('/districts', [MapDataController::class, 'districts']);
    Route::get('/district-bounds', [MapDataController::class, 'districtBounds']);

    // 基本資料路由 - 使用 MapDataController
    Route::get('/heatmap', [MapDataController::class, 'heatmapData']);

    // AI 相關路由 - 使用 MapAIController
    Route::get('/clusters', [MapAIController::class, 'clusters']);
    Route::get('/ai-heatmap', [MapAIController::class, 'aiHeatmap']);
    Route::get('/district-stats', [MapDataController::class, 'districtStats']);
    Route::get('/price-analysis', [MapDataController::class, 'priceAnalysis']);
    Route::get('/predict-prices', [MapAIController::class, 'predictPrices']);
    Route::get('/optimized-data', [MapAIController::class, 'optimizedData']);

    // 通知相關路由 - 使用 MapNotificationController
    Route::post('/notify', [MapNotificationController::class, 'sendNotification']);
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

// AI Prediction API
Route::prefix('ai-prediction')->group(function () {
    Route::post('/predict', [AIPredictionController::class, 'predict']);
    Route::get('/trends', [AIPredictionController::class, 'trends']);
    Route::get('/dashboard', [AIPredictionController::class, 'dashboard']);
});

// Recommendation API
Route::prefix('recommendations')->group(function () {
    Route::get('/personalized', [RecommendationController::class, 'personalized']);
    Route::get('/trending', [RecommendationController::class, 'trending']);
    Route::get('/dashboard', [RecommendationController::class, 'dashboard']);
});

// Risk Assessment API
Route::prefix('risk-assessment')->group(function () {
    Route::post('/assess', [RiskAssessmentController::class, 'assess']);
    Route::post('/batch-assess', [RiskAssessmentController::class, 'batchAssess']);
    Route::get('/trends', [RiskAssessmentController::class, 'trends']);
    Route::get('/dashboard', [RiskAssessmentController::class, 'dashboard']);
});

// Anomaly Detection API
Route::prefix('anomaly-detection')->group(function () {
    Route::get('/price-anomalies', [AnomalyDetectionController::class, 'detectPriceAnomalies']);
    Route::get('/market-anomalies', [AnomalyDetectionController::class, 'detectMarketAnomalies']);
    Route::get('/dashboard', [AnomalyDetectionController::class, 'dashboard']);
});

// Dashboard API
Route::prefix('dashboard')->group(function () {
    Route::get('/statistics', [DashboardController::class, 'getStatistics']);
    Route::get('/quick-actions', [DashboardController::class, 'getQuickActions']);
});
