<?php

namespace App\Http\Controllers;

use App\Services\ErrorTrackingService;
use App\Services\UserBehaviorTrackingService;
use App\Services\PerformanceMonitor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PerformanceDashboardController extends Controller
{
    public function __construct(
        private ErrorTrackingService $errorTracking,
        private UserBehaviorTrackingService $behaviorTracking,
        private PerformanceMonitor $performanceMonitor
    ) {}

    /**
     * 獲取儀表板總覽資料
     */
    public function getOverview(Request $request): JsonResponse
    {
        $timeRange = $request->get('timeRange', '24h');
        
        $errorStats = $this->errorTracking->getErrorStats($timeRange);
        $behaviorStats = $this->behaviorTracking->getUserBehaviorStats($timeRange);
        $performanceMetrics = $this->performanceMonitor->getCurrentMetrics();

        return response()->json([
            'success' => true,
            'data' => [
                'time_range' => $timeRange,
                'performance' => $performanceMetrics,
                'errors' => $errorStats,
                'user_behavior' => $behaviorStats,
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
        $metrics = $this->performanceMonitor->getMetrics($timeRange);

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
        
        if (!$sessionId) {
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
        $metrics = $this->performanceMonitor->getCurrentMetrics();
        
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
        $performanceMetrics = $this->performanceMonitor->getCurrentMetrics();
        
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
}
