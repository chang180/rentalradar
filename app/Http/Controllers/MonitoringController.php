<?php

namespace App\Http\Controllers;

use App\Services\SystemHealthMonitor;
use App\Services\ErrorDetectionSystem;
use App\Services\PerformanceAnalyzer;
use App\Services\AutoRepairSystem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class MonitoringController extends Controller
{
    private SystemHealthMonitor $healthMonitor;
    private ErrorDetectionSystem $errorDetector;
    private PerformanceAnalyzer $performanceAnalyzer;
    private AutoRepairSystem $autoRepair;

    public function __construct(
        SystemHealthMonitor $healthMonitor,
        ErrorDetectionSystem $errorDetector,
        PerformanceAnalyzer $performanceAnalyzer,
        AutoRepairSystem $autoRepair
    ) {
        $this->healthMonitor = $healthMonitor;
        $this->errorDetector = $errorDetector;
        $this->performanceAnalyzer = $performanceAnalyzer;
        $this->autoRepair = $autoRepair;
    }

    /**
     * 獲取系統健康狀態
     */
    public function getSystemHealth(): JsonResponse
    {
        try {
            $health = $this->healthMonitor->getSystemHealth();
            
            return response()->json([
                'success' => true,
                'data' => $health,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取系統健康狀態失敗: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * 獲取核心指標
     */
    public function getCoreMetrics(): JsonResponse
    {
        try {
            $metrics = $this->healthMonitor->getCoreMetrics();
            
            return response()->json([
                'success' => true,
                'data' => $metrics,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取核心指標失敗: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * 獲取應用程式指標
     */
    public function getApplicationMetrics(): JsonResponse
    {
        try {
            $metrics = $this->healthMonitor->getApplicationMetrics();
            
            return response()->json([
                'success' => true,
                'data' => $metrics,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取應用程式指標失敗: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * 檢測系統錯誤
     */
    public function detectErrors(): JsonResponse
    {
        try {
            $errors = $this->errorDetector->detectErrors();
            $categorizedErrors = $this->errorDetector->categorizeErrors($errors);
            
            // 更新錯誤統計
            $this->errorDetector->updateErrorStatistics($errors);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'errors' => $errors,
                    'categorized' => $categorizedErrors,
                    'total_count' => count($errors),
                    'critical_count' => count($categorizedErrors['critical']),
                    'warning_count' => count($categorizedErrors['warning']),
                    'info_count' => count($categorizedErrors['info'])
                ],
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '錯誤檢測失敗: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * 發送警報
     */
    public function sendAlerts(Request $request): JsonResponse
    {
        try {
            $errors = $request->input('errors', []);
            
            if (empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => '沒有錯誤需要發送警報',
                    'timestamp' => now()->toISOString()
                ], 400);
            }
            
            $this->errorDetector->sendAlerts($errors);
            
            return response()->json([
                'success' => true,
                'message' => '警報發送成功',
                'alerts_sent' => count($errors),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '警報發送失敗: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * 分析系統效能
     */
    public function analyzePerformance(): JsonResponse
    {
        try {
            $performance = $this->performanceAnalyzer->analyzePerformance();
            
            return response()->json([
                'success' => true,
                'data' => $performance,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '效能分析失敗: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * 生成優化建議
     */
    public function generateOptimizationSuggestions(): JsonResponse
    {
        try {
            $suggestions = $this->performanceAnalyzer->generateOptimizationSuggestions();
            
            return response()->json([
                'success' => true,
                'data' => $suggestions,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '生成優化建議失敗: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * 執行自動修復
     */
    public function executeAutoRepair(Request $request): JsonResponse
    {
        try {
            $issues = $request->input('issues', []);
            
            if (empty($issues)) {
                return response()->json([
                    'success' => false,
                    'message' => '沒有問題需要修復',
                    'timestamp' => now()->toISOString()
                ], 400);
            }
            
            $repairResults = $this->autoRepair->executeAutoRepair($issues);
            
            return response()->json([
                'success' => true,
                'data' => $repairResults,
                'repairs_executed' => count($repairResults),
                'successful_repairs' => count(array_filter($repairResults, fn($r) => $r['success'])),
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '自動修復失敗: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * 驗證修復結果
     */
    public function verifyRepair(Request $request): JsonResponse
    {
        try {
            $repairType = $request->input('repair_type');
            $originalIssue = $request->input('original_issue');
            
            if (!$repairType || !$originalIssue) {
                return response()->json([
                    'success' => false,
                    'message' => '缺少修復類型或原始問題',
                    'timestamp' => now()->toISOString()
                ], 400);
            }
            
            $verification = $this->autoRepair->verifyRepair($repairType, $originalIssue);
            
            return response()->json([
                'success' => true,
                'data' => $verification,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '修復驗證失敗: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * 獲取監控儀表板資料
     */
    public function getDashboardData(): JsonResponse
    {
        try {
            $cacheKey = 'monitoring_dashboard_data';
            $cachedData = Cache::get($cacheKey);
            
            if ($cachedData && now()->diffInMinutes($cachedData['timestamp']) < 5) {
                return response()->json([
                    'success' => true,
                    'data' => $cachedData,
                    'cached' => true,
                    'timestamp' => now()->toISOString()
                ]);
            }
            
            // 獲取所有監控資料
            $systemHealth = $this->healthMonitor->getSystemHealth();
            $errors = $this->errorDetector->detectErrors();
            $performance = $this->performanceAnalyzer->analyzePerformance();
            $repairStats = $this->autoRepair->getRepairStatistics();
            
            $dashboardData = [
                'system_health' => $systemHealth,
                'errors' => $errors,
                'performance' => $performance,
                'repair_statistics' => $repairStats,
                'timestamp' => now()->toISOString()
            ];
            
            // 快取資料 5 分鐘
            Cache::put($cacheKey, $dashboardData, 300);
            
            return response()->json([
                'success' => true,
                'data' => $dashboardData,
                'cached' => false,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取儀表板資料失敗: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * 獲取歷史趨勢資料
     */
    public function getHistoricalTrends(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', '24h'); // 24h, 7d, 30d
            $metric = $request->input('metric', 'all');
            
            $trends = $this->getTrendData($period, $metric);
            
            return response()->json([
                'success' => true,
                'data' => $trends,
                'period' => $period,
                'metric' => $metric,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取歷史趨勢失敗: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * 獲取警報歷史
     */
    public function getAlertHistory(Request $request): JsonResponse
    {
        try {
            $limit = $request->input('limit', 50);
            $severity = $request->input('severity', 'all');
            
            $alerts = $this->getAlertHistoryData($limit, $severity);
            
            return response()->json([
                'success' => true,
                'data' => $alerts,
                'limit' => $limit,
                'severity' => $severity,
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取警報歷史失敗: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * 獲取趨勢資料
     */
    private function getTrendData(string $period, string $metric): array
    {
        $cacheKey = "trends_{$period}_{$metric}";
        $trends = Cache::get($cacheKey, []);
        
        if (empty($trends)) {
            // 生成模擬趨勢資料
            $trends = $this->generateMockTrendData($period, $metric);
            Cache::put($cacheKey, $trends, 300);
        }
        
        return $trends;
    }

    /**
     * 生成模擬趨勢資料
     */
    private function generateMockTrendData(string $period, string $metric): array
    {
        $dataPoints = [];
        $now = now();
        
        switch ($period) {
            case '24h':
                $interval = 60; // 1 小時間隔
                $points = 24;
                break;
            case '7d':
                $interval = 3600; // 1 小時間隔
                $points = 168;
                break;
            case '30d':
                $interval = 86400; // 1 天間隔
                $points = 30;
                break;
            default:
                $interval = 60;
                $points = 24;
        }
        
        for ($i = $points; $i >= 0; $i--) {
            $timestamp = $now->copy()->subSeconds($i * $interval);
            
            $dataPoints[] = [
                'timestamp' => $timestamp->toISOString(),
                'cpu_usage' => rand(20, 80),
                'memory_usage' => rand(30, 70),
                'disk_usage' => rand(40, 90),
                'response_time' => rand(100, 2000),
                'error_rate' => rand(0, 5),
                'active_users' => rand(10, 100)
            ];
        }
        
        return $dataPoints;
    }

    /**
     * 獲取警報歷史資料
     */
    private function getAlertHistoryData(int $limit, string $severity): array
    {
        $cacheKey = "alert_history_{$limit}_{$severity}";
        $alerts = Cache::get($cacheKey, []);
        
        if (empty($alerts)) {
            // 生成模擬警報歷史
            $alerts = $this->generateMockAlertHistory($limit, $severity);
            Cache::put($cacheKey, $alerts, 300);
        }
        
        return $alerts;
    }

    /**
     * 生成模擬警報歷史
     */
    private function generateMockAlertHistory(int $limit, string $severity): array
    {
        $alerts = [];
        $severities = ['critical', 'warning', 'info'];
        
        for ($i = 0; $i < $limit; $i++) {
            $alertSeverity = $severity === 'all' ? $severities[array_rand($severities)] : $severity;
            
            $alerts[] = [
                'id' => uniqid('alert_'),
                'severity' => $alertSeverity,
                'message' => "系統警報 #{$i}",
                'metric' => ['cpu_usage', 'memory_usage', 'disk_usage', 'error_rate'][array_rand(['cpu_usage', 'memory_usage', 'disk_usage', 'error_rate'])],
                'value' => rand(50, 100),
                'threshold' => rand(70, 90),
                'timestamp' => now()->subMinutes(rand(0, 1440))->toISOString(),
                'resolved' => rand(0, 1) === 1
            ];
        }
        
        // 按時間排序
        usort($alerts, fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));
        
        return $alerts;
    }
}
