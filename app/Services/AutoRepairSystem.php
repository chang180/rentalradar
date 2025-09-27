<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class AutoRepairSystem
{
    private array $repairStrategies;
    private array $repairHistory;

    public function __construct()
    {
        $this->repairStrategies = [
            'clear_cache' => 'clearExpiredCache',
            'restart_services' => 'restartServices',
            'fix_permissions' => 'fixPermissions',
            'cleanup_logs' => 'cleanupLogs',
            'optimize_database' => 'optimizeDatabase',
            'clear_sessions' => 'clearSessions',
            'restart_queue' => 'restartQueue',
            'fix_storage_links' => 'fixStorageLinks'
        ];

        $this->repairHistory = [];
    }

    /**
     * 執行自動修復
     */
    public function executeAutoRepair(array $issues): array
    {
        $repairResults = [];
        
        foreach ($issues as $issue) {
            $repairType = $this->determineRepairType($issue);
            
            if ($repairType && isset($this->repairStrategies[$repairType])) {
                $result = $this->executeRepair($repairType, $issue);
                $repairResults[] = $result;
                
                // 記錄修復歷史
                $this->recordRepairHistory($repairType, $result);
            }
        }
        
        return $repairResults;
    }

    /**
     * 確定修復類型
     */
    private function determineRepairType(array $issue): ?string
    {
        $metric = $issue['metric'] ?? '';
        $severity = $issue['severity'] ?? '';
        
        // 根據指標和嚴重程度確定修復策略
        switch ($metric) {
            case 'memory_usage':
                return 'clear_cache';
            case 'disk_usage':
                return 'cleanup_logs';
            case 'queue_size':
                return 'restart_queue';
            case 'database_connections':
                return 'optimize_database';
            case 'response_time':
                return 'restart_services';
            default:
                return null;
        }
    }

    /**
     * 執行修復
     */
    private function executeRepair(string $repairType, array $issue): array
    {
        $startTime = microtime(true);
        
        try {
            $method = $this->repairStrategies[$repairType];
            $result = $this->$method($issue);
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'repair_type' => $repairType,
                'success' => true,
                'execution_time' => $executionTime,
                'message' => $result['message'] ?? '修復成功',
                'details' => $result['details'] ?? [],
                'timestamp' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error("Auto repair failed for {$repairType}: " . $e->getMessage());
            
            return [
                'repair_type' => $repairType,
                'success' => false,
                'execution_time' => $executionTime,
                'message' => '修復失敗: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * 清理過期快取
     */
    private function clearExpiredCache(array $issue): array
    {
        try {
            // 清理應用程式快取
            Artisan::call('cache:clear');
            
            // 清理配置快取
            Artisan::call('config:clear');
            
            // 清理路由快取
            Artisan::call('route:clear');
            
            // 清理視圖快取
            Artisan::call('view:clear');
            
            // 如果使用 Redis，清理過期鍵
            if (config('cache.default') === 'redis') {
                $this->clearExpiredRedisKeys();
            }
            
            return [
                'message' => '快取清理完成',
                'details' => [
                    'cache_cleared' => true,
                    'config_cleared' => true,
                    'route_cleared' => true,
                    'view_cleared' => true
                ]
            ];
        } catch (\Exception $e) {
            throw new \Exception('快取清理失敗: ' . $e->getMessage());
        }
    }

    /**
     * 重啟服務
     */
    private function restartServices(array $issue): array
    {
        try {
            // 重啟佇列工作程序
            Artisan::call('queue:restart');
            
            // 重啟 Horizon（如果使用）
            if (class_exists('\Laravel\Horizon\Horizon')) {
                Artisan::call('horizon:terminate');
            }
            
            return [
                'message' => '服務重啟完成',
                'details' => [
                    'queue_restarted' => true,
                    'horizon_restarted' => class_exists('\Laravel\Horizon\Horizon')
                ]
            ];
        } catch (\Exception $e) {
            throw new \Exception('服務重啟失敗: ' . $e->getMessage());
        }
    }

    /**
     * 修復權限
     */
    private function fixPermissions(array $issue): array
    {
        try {
            $storagePath = storage_path();
            $bootstrapCachePath = bootstrap_path('cache');
            
            // 修復儲存目錄權限
            $this->setDirectoryPermissions($storagePath, 0755);
            
            // 修復快取目錄權限
            $this->setDirectoryPermissions($bootstrapCachePath, 0755);
            
            // 修復日誌目錄權限
            $this->setDirectoryPermissions(storage_path('logs'), 0755);
            
            return [
                'message' => '權限修復完成',
                'details' => [
                    'storage_permissions' => 'fixed',
                    'cache_permissions' => 'fixed',
                    'logs_permissions' => 'fixed'
                ]
            ];
        } catch (\Exception $e) {
            throw new \Exception('權限修復失敗: ' . $e->getMessage());
        }
    }

    /**
     * 清理日誌
     */
    private function cleanupLogs(array $issue): array
    {
        try {
            $logsPath = storage_path('logs');
            $cleanedFiles = [];
            $freedSpace = 0;
            
            // 清理超過 7 天的日誌檔案
            $files = File::files($logsPath);
            $cutoffDate = now()->subDays(7);
            
            foreach ($files as $file) {
                if ($file->getMTime() < $cutoffDate->timestamp) {
                    $fileSize = $file->getSize();
                    File::delete($file->getPathname());
                    $cleanedFiles[] = $file->getFilename();
                    $freedSpace += $fileSize;
                }
            }
            
            // 清理 Laravel 日誌
            $laravelLog = storage_path('logs/laravel.log');
            if (File::exists($laravelLog) && File::size($laravelLog) > 10 * 1024 * 1024) { // 超過 10MB
                File::put($laravelLog, '');
                $cleanedFiles[] = 'laravel.log';
            }
            
            return [
                'message' => '日誌清理完成',
                'details' => [
                    'files_cleaned' => count($cleanedFiles),
                    'space_freed' => $this->formatBytes($freedSpace),
                    'cleaned_files' => $cleanedFiles
                ]
            ];
        } catch (\Exception $e) {
            throw new \Exception('日誌清理失敗: ' . $e->getMessage());
        }
    }

    /**
     * 優化資料庫
     */
    private function optimizeDatabase(array $issue): array
    {
        try {
            // 優化資料庫表
            $tables = ['users', 'properties', 'cache', 'sessions', 'jobs'];
            $optimizedTables = [];
            
            foreach ($tables as $table) {
                try {
                    DB::statement("OPTIMIZE TABLE {$table}");
                    $optimizedTables[] = $table;
                } catch (\Exception $e) {
                    Log::warning("Failed to optimize table {$table}: " . $e->getMessage());
                }
            }
            
            // 清理過期的快取和會話
            $this->cleanupExpiredCache();
            $this->cleanupExpiredSessions();
            
            return [
                'message' => '資料庫優化完成',
                'details' => [
                    'optimized_tables' => $optimizedTables,
                    'cache_cleaned' => true,
                    'sessions_cleaned' => true
                ]
            ];
        } catch (\Exception $e) {
            throw new \Exception('資料庫優化失敗: ' . $e->getMessage());
        }
    }

    /**
     * 清理會話
     */
    private function clearSessions(array $issue): array
    {
        try {
            // 清理過期會話
            $this->cleanupExpiredSessions();
            
            // 清理會話檔案（如果使用檔案會話）
            if (config('session.driver') === 'file') {
                $sessionsPath = storage_path('framework/sessions');
                if (File::exists($sessionsPath)) {
                    $files = File::files($sessionsPath);
                    $cleanedCount = 0;
                    
                    foreach ($files as $file) {
                        if ($file->getMTime() < now()->subDays(1)->timestamp) {
                            File::delete($file->getPathname());
                            $cleanedCount++;
                        }
                    }
                    
                    return [
                        'message' => '會話清理完成',
                        'details' => [
                            'expired_sessions_cleaned' => true,
                            'session_files_cleaned' => $cleanedCount
                        ]
                    ];
                }
            }
            
            return [
                'message' => '會話清理完成',
                'details' => [
                    'expired_sessions_cleaned' => true
                ]
            ];
        } catch (\Exception $e) {
            throw new \Exception('會話清理失敗: ' . $e->getMessage());
        }
    }

    /**
     * 重啟佇列
     */
    private function restartQueue(array $issue): array
    {
        try {
            // 重啟佇列工作程序
            Artisan::call('queue:restart');
            
            // 如果使用 Horizon，重啟 Horizon
            if (class_exists('\Laravel\Horizon\Horizon')) {
                Artisan::call('horizon:terminate');
            }
            
            return [
                'message' => '佇列重啟完成',
                'details' => [
                    'queue_restarted' => true,
                    'horizon_restarted' => class_exists('\Laravel\Horizon\Horizon')
                ]
            ];
        } catch (\Exception $e) {
            throw new \Exception('佇列重啟失敗: ' . $e->getMessage());
        }
    }

    /**
     * 修復儲存連結
     */
    private function fixStorageLinks(array $issue): array
    {
        try {
            // 建立儲存連結
            Artisan::call('storage:link');
            
            return [
                'message' => '儲存連結修復完成',
                'details' => [
                    'storage_linked' => true
                ]
            ];
        } catch (\Exception $e) {
            throw new \Exception('儲存連結修復失敗: ' . $e->getMessage());
        }
    }

    /**
     * 驗證修復結果
     */
    public function verifyRepair(string $repairType, array $originalIssue): array
    {
        $verificationResults = [];
        
        try {
            // 重新檢查原始問題
            $systemHealth = app(SystemHealthMonitor::class)->getSystemHealth();
            $currentMetrics = $systemHealth['core_metrics'];
            
            $originalMetric = $originalIssue['metric'];
            $originalValue = $originalIssue['value'];
            $currentValue = $currentMetrics[$originalMetric] ?? 0;
            
            $improvement = $this->calculateImprovement($originalValue, $currentValue);
            
            $verificationResults = [
                'repair_type' => $repairType,
                'original_value' => $originalValue,
                'current_value' => $currentValue,
                'improvement' => $improvement,
                'success' => $improvement > 0,
                'system_health' => $systemHealth['health_score'],
                'timestamp' => now()->toISOString()
            ];
            
        } catch (\Exception $e) {
            $verificationResults = [
                'repair_type' => $repairType,
                'success' => false,
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
        
        return $verificationResults;
    }

    /**
     * 獲取修復統計
     */
    public function getRepairStatistics(): array
    {
        $cacheKey = 'auto_repair_statistics';
        $statistics = Cache::get($cacheKey, [
            'total_repairs' => 0,
            'successful_repairs' => 0,
            'failed_repairs' => 0,
            'average_repair_time' => 0,
            'most_common_repairs' => [],
            'last_repair' => null
        ]);
        
        return $statistics;
    }

    /**
     * 更新修復統計
     */
    private function updateRepairStatistics(array $repairResult): void
    {
        $cacheKey = 'auto_repair_statistics';
        $statistics = Cache::get($cacheKey, [
            'total_repairs' => 0,
            'successful_repairs' => 0,
            'failed_repairs' => 0,
            'average_repair_time' => 0,
            'most_common_repairs' => [],
            'last_repair' => null
        ]);
        
        $statistics['total_repairs']++;
        
        if ($repairResult['success']) {
            $statistics['successful_repairs']++;
        } else {
            $statistics['failed_repairs']++;
        }
        
        // 更新平均修復時間
        $totalTime = $statistics['average_repair_time'] * ($statistics['total_repairs'] - 1);
        $statistics['average_repair_time'] = ($totalTime + $repairResult['execution_time']) / $statistics['total_repairs'];
        
        // 更新最常見的修復類型
        $repairType = $repairResult['repair_type'];
        if (!isset($statistics['most_common_repairs'][$repairType])) {
            $statistics['most_common_repairs'][$repairType] = 0;
        }
        $statistics['most_common_repairs'][$repairType]++;
        
        $statistics['last_repair'] = $repairResult['timestamp'];
        
        Cache::put($cacheKey, $statistics, 3600);
    }

    /**
     * 記錄修復歷史
     */
    private function recordRepairHistory(string $repairType, array $result): void
    {
        $historyKey = 'repair_history_' . now()->format('Y-m-d');
        $history = Cache::get($historyKey, []);
        
        $history[] = [
            'repair_type' => $repairType,
            'success' => $result['success'],
            'execution_time' => $result['execution_time'],
            'timestamp' => $result['timestamp']
        ];
        
        // 只保留最近 100 條記錄
        if (count($history) > 100) {
            $history = array_slice($history, -100);
        }
        
        Cache::put($historyKey, $history, 86400); // 保留 24 小時
    }

    /**
     * 計算改善程度
     */
    private function calculateImprovement(float $original, float $current): float
    {
        if ($original == 0) {
            return 0;
        }
        
        return round((($original - $current) / $original) * 100, 2);
    }

    /**
     * 設定目錄權限
     */
    private function setDirectoryPermissions(string $path, int $permissions): void
    {
        if (File::exists($path)) {
            chmod($path, $permissions);
            
            // 遞歸設定子目錄權限
            $directories = File::directories($path);
            foreach ($directories as $directory) {
                $this->setDirectoryPermissions($directory, $permissions);
            }
        }
    }

    /**
     * 清理過期的 Redis 鍵
     */
    private function clearExpiredRedisKeys(): void
    {
        try {
            if (config('cache.default') === 'redis') {
                // 清理過期的快取鍵
                $keys = Redis::keys('*');
                $expiredCount = 0;
                
                foreach ($keys as $key) {
                    $ttl = Redis::ttl($key);
                    if ($ttl === -1) { // 沒有設定過期時間的鍵
                        Redis::expire($key, 3600); // 設定 1 小時過期
                        $expiredCount++;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear expired Redis keys: ' . $e->getMessage());
        }
    }

    /**
     * 清理過期快取
     */
    private function cleanupExpiredCache(): void
    {
        try {
            // 清理過期的快取條目
            DB::table('cache')->where('expiration', '<', now()->timestamp)->delete();
        } catch (\Exception $e) {
            Log::warning('Failed to cleanup expired cache: ' . $e->getMessage());
        }
    }

    /**
     * 清理過期會話
     */
    private function cleanupExpiredSessions(): void
    {
        try {
            // 清理過期的會話
            DB::table('sessions')->where('last_activity', '<', now()->subMinutes(config('session.lifetime', 120))->timestamp)->delete();
        } catch (\Exception $e) {
            Log::warning('Failed to cleanup expired sessions: ' . $e->getMessage());
        }
    }

    /**
     * 格式化位元組
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
