<?php

namespace App\Http\Controllers;

use App\Services\ErrorTrackingService;
use App\Services\UserBehaviorTrackingService;
use App\Support\PerformanceMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerformanceDashboardController extends Controller
{
    public function __construct(
        private ErrorTrackingService $errorTracking,
        private UserBehaviorTrackingService $behaviorTracking
    ) {}

    /**
     * 獲取儀表板總覽資料
     */
    public function getOverview(Request $request): JsonResponse
    {
        $timeRange = $request->get('timeRange', '24h');

        $errorStats = $this->errorTracking->getErrorStats($timeRange);
        $behaviorStats = $this->behaviorTracking->getUserBehaviorStats($timeRange);
        $performanceMetrics = PerformanceMonitor::start('dashboard')->getCurrentMetrics();

        // 生成模擬的歷史資料
        $performanceHistory = $this->generateMockHistoryData($timeRange);
        $errorLogs = $this->generateMockErrorLogs();
        $userBehaviors = $this->generateMockUserBehaviors();

        return response()->json([
            'success' => true,
            'data' => [
                'time_range' => $timeRange,
                'performance' => $performanceHistory,
                'errors' => $errorLogs,
                'user_behavior' => $userBehaviors,
                'timestamp' => now()->timestamp,
            ],
        ]);
    }

    /**
     * 獲取效能指標
     */
    public function getPerformanceMetrics(Request $request): JsonResponse
    {
        $timeRange = $request->get('timeRange', '24h');
        $metrics = PerformanceMonitor::start('performance')->getMetrics($timeRange);

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }

    /**
     * 獲取錯誤日誌
     */
    public function getErrorLogs(Request $request): JsonResponse
    {
        $filters = $request->only(['level', 'since', 'user_id']);
        $errors = $this->errorTracking->getErrors($filters);

        return response()->json([
            'success' => true,
            'data' => [
                'errors' => $errors,
                'total' => count($errors),
                'filters' => $filters,
            ],
        ]);
    }

    /**
     * 獲取使用者行為
     */
    public function getUserBehaviors(Request $request): JsonResponse
    {
        $filters = $request->only(['user_id', 'session_id', 'action', 'since']);
        $behaviors = $this->behaviorTracking->getBehaviors($filters);

        return response()->json([
            'success' => true,
            'data' => [
                'behaviors' => $behaviors,
                'total' => count($behaviors),
                'filters' => $filters,
            ],
        ]);
    }

    /**
     * 獲取會話分析
     */
    public function getSessionAnalysis(Request $request): JsonResponse
    {
        $sessionId = $request->get('session_id');

        if (! $sessionId) {
            return response()->json([
                'success' => false,
                'message' => 'Session ID is required',
            ], 400);
        }

        $analysis = $this->behaviorTracking->getSessionAnalysis($sessionId);

        return response()->json([
            'success' => true,
            'data' => $analysis,
        ]);
    }

    /**
     * 記錄錯誤
     */
    public function logError(Request $request): JsonResponse
    {
        $errorData = $request->validate([
            'level' => 'required|string|in:error,warning,info',
            'message' => 'required|string',
            'stack' => 'nullable|string',
            'url' => 'nullable|string',
            'user_id' => 'nullable|string',
            'session_id' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $errorData['ip_address'] = $request->ip();
        $errorData['user_agent'] = $request->userAgent();

        $this->errorTracking->logError($errorData);

        return response()->json([
            'success' => true,
            'message' => 'Error logged successfully',
        ]);
    }

    /**
     * 追蹤使用者行為
     */
    public function trackBehavior(Request $request): JsonResponse
    {
        $behaviorData = $request->validate([
            'action' => 'required|string',
            'page' => 'required|string',
            'duration' => 'nullable|integer|min:0',
            'user_id' => 'nullable|string',
            'session_id' => 'nullable|string',
            'metadata' => 'nullable|array',
        ]);

        $behaviorData['ip_address'] = $request->ip();
        $behaviorData['user_agent'] = $request->userAgent();

        $this->behaviorTracking->trackBehavior($behaviorData);

        return response()->json([
            'success' => true,
            'message' => 'Behavior tracked successfully',
        ]);
    }

    /**
     * 清理舊資料
     */
    public function cleanupOldData(Request $request): JsonResponse
    {
        $daysToKeep = $request->get('days_to_keep', 7);

        $errorCount = $this->errorTracking->cleanupOldErrors($daysToKeep);
        $behaviorCount = $this->behaviorTracking->cleanupOldBehaviors($daysToKeep * 4); // 保留行為資料更久

        return response()->json([
            'success' => true,
            'data' => [
                'errors_cleaned' => $errorCount,
                'behaviors_cleaned' => $behaviorCount,
                'days_kept' => $daysToKeep,
            ],
        ]);
    }

    /**
     * 獲取即時效能指標
     */
    public function getRealtimeMetrics(): JsonResponse
    {
        $metrics = PerformanceMonitor::start('realtime')->getCurrentMetrics();

        return response()->json([
            'success' => true,
            'data' => $metrics,
            'timestamp' => now()->timestamp,
        ]);
    }

    /**
     * 獲取系統健康狀態
     */
    public function getSystemHealth(): JsonResponse
    {
        $errorStats = $this->errorTracking->getErrorStats('1h');
        $performanceMetrics = PerformanceMonitor::start('system-health')->getCurrentMetrics();

        $healthScore = $this->calculateHealthScore($errorStats, $performanceMetrics);
        $status = $this->getHealthStatus($healthScore);

        return response()->json([
            'success' => true,
            'data' => [
                'health_score' => $healthScore,
                'status' => $status,
                'error_rate' => $errorStats['error_rate'] ?? 0,
                'response_time' => $performanceMetrics['response_time'] ?? 0,
                'memory_usage' => $performanceMetrics['memory_usage'] ?? 0,
                'timestamp' => now()->timestamp,
            ],
        ]);
    }

    /**
     * 計算健康分數
     */
    private function calculateHealthScore(array $errorStats, array $performanceMetrics): int
    {
        $score = 100;

        // 錯誤率影響
        $errorRate = $errorStats['error_rate'] ?? 0;
        if ($errorRate > 5) {
            $score -= 30;
        } elseif ($errorRate > 1) {
            $score -= 15;
        }

        // 響應時間影響
        $responseTime = $performanceMetrics['response_time'] ?? 0;
        if ($responseTime > 1000) {
            $score -= 25;
        } elseif ($responseTime > 500) {
            $score -= 10;
        }

        // 記憶體使用影響
        $memoryUsage = $performanceMetrics['memory_usage'] ?? 0;
        if ($memoryUsage > 200) {
            $score -= 20;
        } elseif ($memoryUsage > 100) {
            $score -= 10;
        }

        return max(0, $score);
    }

    /**
     * 獲取健康狀態
     */
    private function getHealthStatus(int $healthScore): string
    {
        if ($healthScore >= 80) {
            return 'healthy';
        } elseif ($healthScore >= 60) {
            return 'warning';
        } else {
            return 'critical';
        }
    }

    /**
     * 生成模擬歷史資料
     */
    private function generateMockHistoryData(string $timeRange): array
    {
        $data = [];
        $now = now();
        $points = 24; // 24 個資料點

        for ($i = $points; $i >= 0; $i--) {
            $timestamp = $now->copy()->subMinutes($i * 5)->timestamp;
            $data[] = [
                'timestamp' => $timestamp,
                'responseTime' => rand(50, 300),
                'memoryUsage' => rand(20, 120),
                'queryCount' => rand(5, 50),
                'cacheHitRate' => rand(70, 95),
                'activeConnections' => rand(10, 100),
                'errorRate' => rand(0, 5),
                'throughput' => rand(100, 1000),
            ];
        }

        return $data;
    }

    /**
     * 生成模擬錯誤日誌
     */
    private function generateMockErrorLogs(): array
    {
        $errors = [
            'Database connection timeout',
            'Memory limit exceeded',
            'API rate limit exceeded',
            'File not found: config.php',
            'Invalid JSON response',
            'WebSocket connection failed',
            'Cache key not found',
            'Authentication failed',
        ];

        $logs = [];
        for ($i = 0; $i < 10; $i++) {
            $logs[] = [
                'id' => $i + 1,
                'message' => $errors[array_rand($errors)],
                'level' => ['error', 'warning', 'info'][array_rand([0, 1, 2])],
                'timestamp' => now()->subMinutes(rand(0, 1440))->toISOString(),
                'count' => rand(1, 10),
            ];
        }

        return $logs;
    }

    /**
     * 生成模擬使用者行為
     */
    private function generateMockUserBehaviors(): array
    {
        $actions = [
            'page_view',
            'map_interaction',
            'search_performed',
            'filter_applied',
            'property_clicked',
            'performance_viewed',
        ];

        $behaviors = [];
        for ($i = 0; $i < 15; $i++) {
            $behaviors[] = [
                'id' => $i + 1,
                'action' => $actions[array_rand($actions)],
                'user_id' => rand(1, 100),
                'timestamp' => now()->subMinutes(rand(0, 1440))->toISOString(),
                'metadata' => [
                    'page' => ['/', '/map', '/performance'][array_rand([0, 1, 2])],
                    'duration' => rand(5, 300),
                ],
            ];
        }

        return $behaviors;
    }
}
