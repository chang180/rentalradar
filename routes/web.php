<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Handle favicon requests to prevent 302 redirects
Route::get('/favicon.ico', function () {
    return response()->file(public_path('favicon.ico'));
});

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// 公開地圖路由（直接載入 map 頁面，但有 IP 限制）
Route::get('/public-map', function (Illuminate\Http\Request $request) {
    return Inertia::render('map', [
        'is_public' => true,
        'remaining_seconds' => $request->get('remaining_seconds', 1800),
        'ip_usage_data' => $request->get('ip_usage_data'),
    ]);
})->middleware(App\Http\Middleware\IpRateLimit::class)->name('public.map');

// 公開地圖會話結束 API
Route::post('/api/public-map/session-end', function (Illuminate\Http\Request $request) {
    $ip = $request->ip();
    $sessionKey = "public_map_session_{$ip}";
    $cacheKey = "public_map_usage_{$ip}";

    $currentSession = Cache::get($sessionKey);
    $usageData = Cache::get($cacheKey, [
        'date' => now()->format('Y-m-d'),
        'used_seconds' => 0,
        'sessions' => [],
    ]);

    // 確保資料結構完整
    $usageData = array_merge([
        'date' => now()->format('Y-m-d'),
        'used_seconds' => 0,
        'sessions' => [],
    ], $usageData);

    if ($currentSession && $currentSession['start_time']) {
        // 計算會話持續時間
        $sessionDuration = $currentSession['start_time']->diffInSeconds(now());

        // 更新使用時間
        $usageData['used_seconds'] += $sessionDuration;
        $usageData['sessions'][] = [
            'start_time' => $currentSession['start_time']->toISOString(),
            'end_time' => now()->toISOString(),
            'duration' => $sessionDuration,
        ];

        // 儲存更新後的資料
        Cache::put($cacheKey, $usageData, now()->endOfDay());
        Cache::forget($sessionKey);
    }

    return response()->json(['success' => true]);
});

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

    Route::get('analysis', function () {
        return Inertia::render('MarketAnalysisDashboard');
    })->name('analysis');

    Route::get('monitoring', function () {
        return Inertia::render('MonitoringDashboard');
    })->name('monitoring');
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

    // 監控 API
    Route::prefix('monitoring')->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\MonitoringController::class, 'getDashboardData']);
        Route::get('/health', [App\Http\Controllers\MonitoringController::class, 'getSystemHealth']);
        Route::get('/core-metrics', [App\Http\Controllers\MonitoringController::class, 'getCoreMetrics']);
        Route::get('/app-metrics', [App\Http\Controllers\MonitoringController::class, 'getApplicationMetrics']);
        Route::get('/errors', [App\Http\Controllers\MonitoringController::class, 'detectErrors']);
        Route::post('/alerts', [App\Http\Controllers\MonitoringController::class, 'sendAlerts']);
        Route::get('/performance', [App\Http\Controllers\MonitoringController::class, 'analyzePerformance']);
        Route::get('/optimization-suggestions', [App\Http\Controllers\MonitoringController::class, 'generateOptimizationSuggestions']);
        Route::post('/auto-repair', [App\Http\Controllers\MonitoringController::class, 'executeAutoRepair']);
        Route::post('/verify-repair', [App\Http\Controllers\MonitoringController::class, 'verifyRepair']);
        Route::get('/historical-trends', [App\Http\Controllers\MonitoringController::class, 'getHistoricalTrends']);
        Route::get('/alert-history', [App\Http\Controllers\MonitoringController::class, 'getAlertHistory']);
    });
});
