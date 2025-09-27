<?php

use App\Http\Controllers\MapController;
use Illuminate\Support\Facades\Route;

Route::prefix('map')->name('map.')->group(function () {
    // 基本地圖資料端點
    Route::get('properties', [MapController::class, 'index'])->name('properties');
    Route::get('statistics', [MapController::class, 'statistics'])->name('statistics');
    Route::get('heatmap', [MapController::class, 'heatmapData'])->name('heatmap');
    Route::get('districts', [MapController::class, 'districts'])->name('districts');

    // AI 優化端點
    Route::get('clusters', [MapController::class, 'clusters'])->name('clusters');
    Route::get('ai-heatmap', [MapController::class, 'aiHeatmap'])->name('ai-heatmap');
    Route::post('predict-prices', [MapController::class, 'predictPrices'])->name('predict-prices');
    Route::get('optimized-data', [MapController::class, 'optimizedData'])->name('optimized-data');

    // WebSocket 即時功能端點
    Route::post('notify', [MapController::class, 'sendNotification'])->name('notify');
});