<?php

namespace App\Http\Controllers;

use App\Services\AnomalyDetectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AnomalyDetectionController extends Controller
{
    protected AnomalyDetectionService $anomalyDetectionService;

    public function __construct(AnomalyDetectionService $anomalyDetectionService)
    {
        $this->anomalyDetectionService = $anomalyDetectionService;
    }

    public function detectPriceAnomalies(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $district = $request->query('district');
            $severity = $request->query('severity', 'all');
            $limit = $request->query('limit', 50);

            $anomalies = $this->anomalyDetectionService->detectPriceAnomalies();

            // 根據查詢參數過濾結果
            if ($district) {
                $anomalies = array_filter($anomalies, function ($anomaly) use ($district) {
                    return isset($anomaly['property']['district']) &&
                           $anomaly['property']['district'] === $district;
                });
            }

            if ($severity !== 'all') {
                $anomalies = array_filter($anomalies, function ($anomaly) use ($severity) {
                    return $anomaly['severity'] === $severity;
                });
            }

            // 限制結果數量
            $anomalies = array_slice($anomalies, 0, $limit);

            return response()->json([
                'status' => 'success',
                'anomalies' => $anomalies,
                'total_detected' => count($anomalies),
                'filters' => [
                    'district' => $district,
                    'severity' => $severity,
                    'limit' => $limit,
                ],
                'detected_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Price anomaly detection failed', [
                'user_id' => Auth::id(),
                'filters' => $request->query(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Price anomaly detection failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function detectMarketAnomalies(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $district = $request->query('district');
            $timeframe = $request->query('timeframe', '7d');
            $limit = $request->query('limit', 50);

            $anomalies = $this->anomalyDetectionService->detectMarketAnomalies();

            // 根據查詢參數過濾結果
            if ($district) {
                $anomalies = array_filter($anomalies, function ($anomaly) use ($district) {
                    return isset($anomaly['district']) &&
                           $anomaly['district'] === $district;
                });
            }

            // 限制結果數量
            $anomalies = array_slice($anomalies, 0, $limit);

            return response()->json([
                'status' => 'success',
                'anomalies' => $anomalies,
                'total_detected' => count($anomalies),
                'filters' => [
                    'district' => $district,
                    'timeframe' => $timeframe,
                    'limit' => $limit,
                ],
                'detected_at' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Market anomaly detection failed', [
                'user_id' => Auth::id(),
                'filters' => $request->query(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Market anomaly detection failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function dashboard(): \Inertia\Response
    {
        try {
            // 獲取異常檢測儀表板資料
            $priceAnomalies = $this->anomalyDetectionService->detectPriceAnomalies();
            $marketAnomalies = $this->anomalyDetectionService->detectMarketAnomalies();

            // 統計資料
            $stats = [
                'total_price_anomalies' => count($priceAnomalies),
                'total_market_anomalies' => count($marketAnomalies),
                'high_severity_count' => count(array_filter($priceAnomalies, fn ($a) => $a['severity'] === 'high')),
                'medium_severity_count' => count(array_filter($priceAnomalies, fn ($a) => $a['severity'] === 'medium')),
                'low_severity_count' => count(array_filter($priceAnomalies, fn ($a) => $a['severity'] === 'low')),
                'recent_anomalies' => array_slice($priceAnomalies, 0, 10),
            ];

            return Inertia::render('AnomalyDetectionDashboard', [
                'stats' => $stats,
                'price_anomalies' => array_slice($priceAnomalies, 0, 20),
                'market_anomalies' => array_slice($marketAnomalies, 0, 20),
                'page_title' => '異常檢測儀表板',
                'last_updated' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Anomaly detection dashboard failed', [
                'error' => $e->getMessage(),
            ]);

            return Inertia::render('AnomalyDetectionDashboard', [
                'stats' => [
                    'total_price_anomalies' => 0,
                    'total_market_anomalies' => 0,
                    'high_severity_count' => 0,
                    'medium_severity_count' => 0,
                    'low_severity_count' => 0,
                    'recent_anomalies' => [],
                ],
                'price_anomalies' => [],
                'market_anomalies' => [],
                'page_title' => '異常檢測儀表板',
                'error' => '載入儀表板資料時發生錯誤',
            ]);
        }
    }

    public function getAnomalyDetails(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            // 這裡可以根據 ID 獲取特定異常的詳細資訊
            // 由於我們的異常檢測是即時的，這裡提供一個基本的實作

            return response()->json([
                'status' => 'success',
                'message' => 'Anomaly details endpoint - to be implemented based on stored anomalies',
                'anomaly_id' => $id,
                'note' => 'This endpoint can be enhanced to retrieve stored anomaly details from database',
            ]);

        } catch (\Exception $e) {
            Log::error('Get anomaly details failed', [
                'anomaly_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get anomaly details: '.$e->getMessage(),
            ], 500);
        }
    }

    public function exportAnomalies(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $request->validate([
                'format' => 'required|in:json,csv',
                'type' => 'required|in:price,market,all',
            ]);

            $format = $request->input('format');
            $type = $request->input('type');

            $anomalies = [];

            if ($type === 'price' || $type === 'all') {
                $anomalies['price_anomalies'] = $this->anomalyDetectionService->detectPriceAnomalies();
            }

            if ($type === 'market' || $type === 'all') {
                $anomalies['market_anomalies'] = $this->anomalyDetectionService->detectMarketAnomalies();
            }

            return response()->json([
                'status' => 'success',
                'anomalies' => $anomalies,
                'export_format' => $format,
                'export_type' => $type,
                'exported_at' => now()->toISOString(),
                'note' => 'Data exported in JSON format. CSV conversion can be implemented on frontend.',
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Export anomalies failed', [
                'user_id' => Auth::id(),
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Export failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
