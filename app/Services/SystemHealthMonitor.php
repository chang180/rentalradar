<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SystemHealthMonitor
{
    /**
     * 獲取系統核心指標
     */
    public function getCoreMetrics(): array
    {
        return [
            'cpu_usage' => $this->getCpuUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'database_connections' => $this->getDbConnections(),
            'queue_size' => $this->getQueueSize(),
            'response_time' => $this->getResponseTime(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * 獲取應用程式指標
     */
    public function getApplicationMetrics(): array
    {
        return [
            'active_users' => $this->getActiveUsers(),
            'api_requests' => $this->getApiRequests(),
            'error_rate' => $this->getErrorRate(),
            'cache_hit_rate' => $this->getCacheHitRate(),
            'database_queries' => $this->getDbQueries(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * 獲取系統健康狀態
     */
    public function getSystemHealth(): array
    {
        $coreMetrics = $this->getCoreMetrics();
        $appMetrics = $this->getApplicationMetrics();
        
        $healthScore = $this->calculateHealthScore($coreMetrics, $appMetrics);
        $status = $this->determineHealthStatus($healthScore);
        
        return [
            'health_score' => $healthScore,
            'status' => $status,
            'core_metrics' => $coreMetrics,
            'app_metrics' => $appMetrics,
            'alerts' => $this->getActiveAlerts(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * 獲取 CPU 使用率
     */
    private function getCpuUsage(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return round($load[0] * 100, 2);
        }
        
        return 0.0;
    }

    /**
     * 獲取記憶體使用率
     */
    private function getMemoryUsage(): float
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        
        if ($memoryLimit === '-1') {
            return 0.0;
        }
        
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        return round(($memoryUsage / $memoryLimitBytes) * 100, 2);
    }

    /**
     * 獲取磁碟使用率
     */
    private function getDiskUsage(): float
    {
        $totalSpace = disk_total_space(storage_path());
        $freeSpace = disk_free_space(storage_path());
        
        if ($totalSpace === false || $freeSpace === false) {
            return 0.0;
        }
        
        $usedSpace = $totalSpace - $freeSpace;
        return round(($usedSpace / $totalSpace) * 100, 2);
    }

    /**
     * 獲取資料庫連線數
     */
    private function getDbConnections(): int
    {
        try {
            $connections = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            return (int) ($connections[0]->Value ?? 0);
        } catch (\Exception $e) {
            Log::error('Failed to get database connections: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 獲取佇列大小
     */
    private function getQueueSize(): int
    {
        try {
            if (config('queue.default') === 'redis') {
                return Redis::llen('queues:default');
            }
            return 0;
        } catch (\Exception $e) {
            Log::error('Failed to get queue size: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * 獲取響應時間
     */
    private function getResponseTime(): float
    {
        $start = microtime(true);
        
        // 執行簡單的資料庫查詢來測試響應時間
        try {
            DB::select('SELECT 1');
            return round((microtime(true) - $start) * 1000, 2);
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * 獲取活躍使用者數
     */
    private function getActiveUsers(): int
    {
        $cacheKey = 'active_users_count';
        $activeUsers = Cache::get($cacheKey, 0);
        
        // 如果快取不存在，從資料庫獲取
        if ($activeUsers === 0) {
            try {
                $activeUsers = DB::table('users')
                    ->where('last_activity_at', '>', now()->subMinutes(30))
                    ->count();
                
                Cache::put($cacheKey, $activeUsers, 300); // 快取 5 分鐘
            } catch (\Exception $e) {
                Log::error('Failed to get active users: ' . $e->getMessage());
                return 0;
            }
        }
        
        return $activeUsers;
    }

    /**
     * 獲取 API 請求數
     */
    private function getApiRequests(): int
    {
        $cacheKey = 'api_requests_count';
        $requests = Cache::get($cacheKey, 0);
        
        // 如果快取不存在，從日誌獲取
        if ($requests === 0) {
            try {
                $logFile = storage_path('logs/laravel.log');
                if (file_exists($logFile)) {
                    $content = file_get_contents($logFile);
                    $requests = substr_count($content, 'HTTP/1.1" 200');
                }
                
                Cache::put($cacheKey, $requests, 300);
            } catch (\Exception $e) {
                Log::error('Failed to get API requests: ' . $e->getMessage());
                return 0;
            }
        }
        
        return $requests;
    }

    /**
     * 獲取錯誤率
     */
    private function getErrorRate(): float
    {
        $cacheKey = 'error_rate';
        $errorRate = Cache::get($cacheKey, 0.0);
        
        if ($errorRate === 0.0) {
            try {
                $logFile = storage_path('logs/laravel.log');
                if (file_exists($logFile)) {
                    $content = file_get_contents($logFile);
                    $totalRequests = substr_count($content, 'HTTP/1.1"');
                    $errorRequests = substr_count($content, 'HTTP/1.1" 5') + substr_count($content, 'HTTP/1.1" 4');
                    
                    if ($totalRequests > 0) {
                        $errorRate = round(($errorRequests / $totalRequests) * 100, 2);
                    }
                }
                
                Cache::put($cacheKey, $errorRate, 300);
            } catch (\Exception $e) {
                Log::error('Failed to get error rate: ' . $e->getMessage());
                return 0.0;
            }
        }
        
        return $errorRate;
    }

    /**
     * 獲取快取命中率
     */
    private function getCacheHitRate(): float
    {
        try {
            if (config('cache.default') === 'redis') {
                $info = Redis::info();
                $hits = $info['keyspace_hits'] ?? 0;
                $misses = $info['keyspace_misses'] ?? 0;
                $total = $hits + $misses;
                
                if ($total > 0) {
                    return round(($hits / $total) * 100, 2);
                }
            }
            return 0.0;
        } catch (\Exception $e) {
            Log::error('Failed to get cache hit rate: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * 獲取資料庫查詢數
     */
    private function getDbQueries(): int
    {
        $cacheKey = 'db_queries_count';
        $queries = Cache::get($cacheKey, 0);
        
        if ($queries === 0) {
            try {
                $queries = DB::getQueryLog();
                $count = count($queries);
                Cache::put($cacheKey, $count, 300);
                return $count;
            } catch (\Exception $e) {
                Log::error('Failed to get database queries: ' . $e->getMessage());
                return 0;
            }
        }
        
        return $queries;
    }

    /**
     * 計算健康分數
     */
    private function calculateHealthScore(array $coreMetrics, array $appMetrics): float
    {
        $scores = [];
        
        // CPU 使用率評分 (0-100)
        $cpuScore = max(0, 100 - $coreMetrics['cpu_usage']);
        $scores[] = $cpuScore;
        
        // 記憶體使用率評分 (0-100)
        $memoryScore = max(0, 100 - $coreMetrics['memory_usage']);
        $scores[] = $memoryScore;
        
        // 磁碟使用率評分 (0-100)
        $diskScore = max(0, 100 - $coreMetrics['disk_usage']);
        $scores[] = $diskScore;
        
        // 響應時間評分 (0-100)
        $responseScore = max(0, 100 - ($coreMetrics['response_time'] / 10));
        $scores[] = $responseScore;
        
        // 錯誤率評分 (0-100)
        $errorScore = max(0, 100 - $appMetrics['error_rate']);
        $scores[] = $errorScore;
        
        // 快取命中率評分 (0-100)
        $cacheScore = $appMetrics['cache_hit_rate'];
        $scores[] = $cacheScore;
        
        return round(array_sum($scores) / count($scores), 2);
    }

    /**
     * 確定健康狀態
     */
    private function determineHealthStatus(float $healthScore): string
    {
        if ($healthScore >= 90) {
            return 'excellent';
        } elseif ($healthScore >= 80) {
            return 'good';
        } elseif ($healthScore >= 70) {
            return 'fair';
        } elseif ($healthScore >= 60) {
            return 'poor';
        } else {
            return 'critical';
        }
    }

    /**
     * 獲取活躍警報
     */
    private function getActiveAlerts(): array
    {
        $alerts = [];
        
        $coreMetrics = $this->getCoreMetrics();
        $appMetrics = $this->getApplicationMetrics();
        
        // CPU 使用率警報
        if ($coreMetrics['cpu_usage'] > 80) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'CPU 使用率過高: ' . $coreMetrics['cpu_usage'] . '%',
                'metric' => 'cpu_usage',
                'value' => $coreMetrics['cpu_usage'],
                'threshold' => 80
            ];
        }
        
        // 記憶體使用率警報
        if ($coreMetrics['memory_usage'] > 85) {
            $alerts[] = [
                'type' => 'warning',
                'message' => '記憶體使用率過高: ' . $coreMetrics['memory_usage'] . '%',
                'metric' => 'memory_usage',
                'value' => $coreMetrics['memory_usage'],
                'threshold' => 85
            ];
        }
        
        // 磁碟使用率警報
        if ($coreMetrics['disk_usage'] > 90) {
            $alerts[] = [
                'type' => 'critical',
                'message' => '磁碟使用率過高: ' . $coreMetrics['disk_usage'] . '%',
                'metric' => 'disk_usage',
                'value' => $coreMetrics['disk_usage'],
                'threshold' => 90
            ];
        }
        
        // 錯誤率警報
        if ($appMetrics['error_rate'] > 5) {
            $alerts[] = [
                'type' => 'critical',
                'message' => '錯誤率過高: ' . $appMetrics['error_rate'] . '%',
                'metric' => 'error_rate',
                'value' => $appMetrics['error_rate'],
                'threshold' => 5
            ];
        }
        
        return $alerts;
    }

    /**
     * 轉換記憶體限制為位元組
     */
    private function convertToBytes(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $memoryLimit = (int) $memoryLimit;
        
        switch ($last) {
            case 'g':
                $memoryLimit *= 1024;
            case 'm':
                $memoryLimit *= 1024;
            case 'k':
                $memoryLimit *= 1024;
        }
        
        return $memoryLimit;
    }
}
