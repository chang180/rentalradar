<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ErrorTrackingService
{
    private const CACHE_KEY = 'error_tracking';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * 記錄錯誤
     */
    public function logError(array $errorData): void
    {
        $error = [
            'id' => $this->generateErrorId(),
            'timestamp' => now()->timestamp,
            'level' => $errorData['level'] ?? 'error',
            'message' => $errorData['message'] ?? 'Unknown error',
            'stack' => $errorData['stack'] ?? null,
            'user_agent' => $errorData['user_agent'] ?? null,
            'url' => $errorData['url'] ?? null,
            'user_id' => $errorData['user_id'] ?? null,
            'session_id' => $errorData['session_id'] ?? null,
            'ip_address' => $errorData['ip_address'] ?? null,
            'metadata' => $errorData['metadata'] ?? [],
        ];

        // 記錄到日誌
        $this->logToFile($error);

        // 儲存到快取
        $this->storeInCache($error);

        // 如果是嚴重錯誤，發送通知
        if ($error['level'] === 'error') {
            $this->sendErrorNotification($error);
        }
    }

    /**
     * 獲取錯誤統計
     */
    public function getErrorStats(string $timeRange = '24h'): array
    {
        $cacheKey = self::CACHE_KEY . '_stats_' . $timeRange;
        
        return Cache::remember($cacheKey, 300, function () use ($timeRange) {
            $startTime = $this->getTimeRangeStart($timeRange);
            $errors = $this->getErrorsFromCache($startTime);

            $stats = [
                'total_errors' => count($errors),
                'error_rate' => $this->calculateErrorRate($errors),
                'by_level' => $this->groupByLevel($errors),
                'by_hour' => $this->groupByHour($errors),
                'top_errors' => $this->getTopErrors($errors),
                'recent_errors' => array_slice($errors, -10),
            ];

            return $stats;
        });
    }

    /**
     * 獲取錯誤列表
     */
    public function getErrors(array $filters = []): array
    {
        $errors = $this->getErrorsFromCache();
        
        // 應用過濾器
        if (isset($filters['level'])) {
            $errors = array_filter($errors, fn($error) => $error['level'] === $filters['level']);
        }
        
        if (isset($filters['since'])) {
            $since = is_string($filters['since']) ? strtotime($filters['since']) : $filters['since'];
            $errors = array_filter($errors, fn($error) => $error['timestamp'] >= $since);
        }
        
        if (isset($filters['user_id'])) {
            $errors = array_filter($errors, fn($error) => $error['user_id'] === $filters['user_id']);
        }

        // 排序
        usort($errors, fn($a, $b) => $b['timestamp'] - $a['timestamp']);

        return $errors;
    }

    /**
     * 清理舊錯誤
     */
    public function cleanupOldErrors(int $daysToKeep = 7): int
    {
        $cutoffTime = now()->subDays($daysToKeep)->timestamp;
        $errors = $this->getErrorsFromCache();
        
        $originalCount = count($errors);
        $errors = array_filter($errors, fn($error) => $error['timestamp'] >= $cutoffTime);
        
        $this->storeErrorsInCache($errors);
        
        return $originalCount - count($errors);
    }

    /**
     * 生成錯誤 ID
     */
    private function generateErrorId(): string
    {
        return 'error_' . uniqid() . '_' . time();
    }

    /**
     * 記錄到檔案
     */
    private function logToFile(array $error): void
    {
        $logData = [
            'error_id' => $error['id'],
            'level' => $error['level'],
            'message' => $error['message'],
            'timestamp' => $error['timestamp'],
            'url' => $error['url'],
            'user_id' => $error['user_id'],
        ];

        if ($error['level'] === 'error') {
            Log::error('Application Error', $logData);
        } elseif ($error['level'] === 'warning') {
            Log::warning('Application Warning', $logData);
        } else {
            Log::info('Application Info', $logData);
        }
    }

    /**
     * 儲存到快取
     */
    private function storeInCache(array $error): void
    {
        $errors = $this->getErrorsFromCache();
        $errors[] = $error;
        
        // 只保留最新的 1000 個錯誤
        if (count($errors) > 1000) {
            $errors = array_slice($errors, -1000);
        }
        
        $this->storeErrorsInCache($errors);
    }

    /**
     * 從快取獲取錯誤
     */
    private function getErrorsFromCache(int $since = null): array
    {
        $errors = Cache::get(self::CACHE_KEY, []);
        
        if ($since) {
            $errors = array_filter($errors, fn($error) => $error['timestamp'] >= $since);
        }
        
        return $errors;
    }

    /**
     * 儲存錯誤到快取
     */
    private function storeErrorsInCache(array $errors): void
    {
        Cache::put(self::CACHE_KEY, $errors, self::CACHE_TTL);
    }

    /**
     * 獲取時間範圍開始時間
     */
    private function getTimeRangeStart(string $timeRange): int
    {
        switch ($timeRange) {
            case '1h':
                return now()->subHour()->timestamp;
            case '6h':
                return now()->subHours(6)->timestamp;
            case '24h':
                return now()->subDay()->timestamp;
            case '7d':
                return now()->subDays(7)->timestamp;
            default:
                return now()->subDay()->timestamp;
        }
    }

    /**
     * 計算錯誤率
     */
    private function calculateErrorRate(array $errors): float
    {
        $totalRequests = $this->getTotalRequests();
        $errorCount = count($errors);
        
        return $totalRequests > 0 ? ($errorCount / $totalRequests) * 100 : 0;
    }

    /**
     * 按級別分組
     */
    private function groupByLevel(array $errors): array
    {
        $groups = [];
        foreach ($errors as $error) {
            $level = $error['level'];
            $groups[$level] = ($groups[$level] ?? 0) + 1;
        }
        return $groups;
    }

    /**
     * 按小時分組
     */
    private function groupByHour(array $errors): array
    {
        $groups = [];
        foreach ($errors as $error) {
            $hour = date('H', $error['timestamp']);
            $groups[$hour] = ($groups[$hour] ?? 0) + 1;
        }
        return $groups;
    }

    /**
     * 獲取最常見的錯誤
     */
    private function getTopErrors(array $errors, int $limit = 10): array
    {
        $errorCounts = [];
        foreach ($errors as $error) {
            $message = $error['message'];
            $errorCounts[$message] = ($errorCounts[$message] ?? 0) + 1;
        }
        
        arsort($errorCounts);
        return array_slice($errorCounts, 0, $limit, true);
    }

    /**
     * 獲取總請求數
     */
    private function getTotalRequests(): int
    {
        return Cache::get('total_requests', 1000);
    }

    /**
     * 發送錯誤通知
     */
    private function sendErrorNotification(array $error): void
    {
        // 這裡可以整合通知系統，如郵件、Slack 等
        Log::critical('Critical Error Detected', [
            'error_id' => $error['id'],
            'message' => $error['message'],
            'url' => $error['url'],
            'user_id' => $error['user_id'],
        ]);
    }
}
