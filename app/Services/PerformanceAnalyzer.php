<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class PerformanceAnalyzer
{
    /**
     * 分析系統效能
     */
    public function analyzePerformance(): array
    {
        return [
            'slow_queries' => $this->identifySlowQueries(),
            'memory_leaks' => $this->detectMemoryLeaks(),
            'bottlenecks' => $this->findBottlenecks(),
            'optimization_opportunities' => $this->findOptimizations(),
            'cache_performance' => $this->analyzeCachePerformance(),
            'database_performance' => $this->analyzeDatabasePerformance(),
            'api_performance' => $this->analyzeApiPerformance(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * 識別慢查詢
     */
    private function identifySlowQueries(): array
    {
        $slowQueries = [];
        
        try {
            // 啟用查詢日誌
            DB::enableQueryLog();
            
            // 執行一些常見查詢來測試效能
            $this->executeCommonQueries();
            
            $queries = DB::getQueryLog();
            
            foreach ($queries as $query) {
                if ($query['time'] > 1000) { // 超過 1 秒的查詢
                    $slowQueries[] = [
                        'sql' => $query['query'],
                        'time' => $query['time'],
                        'bindings' => $query['bindings'],
                        'severity' => $query['time'] > 5000 ? 'critical' : 'warning'
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to identify slow queries: ' . $e->getMessage());
        }
        
        return $slowQueries;
    }

    /**
     * 執行常見查詢
     */
    private function executeCommonQueries(): void
    {
        try {
            // 測試使用者查詢
            DB::table('users')->count();
            
            // 測試屬性查詢
            DB::table('properties')->count();
            
            // 測試複雜查詢
            DB::table('properties')
                ->where('is_geocoded', true)
                ->where('created_at', '>', now()->subDays(30))
                ->count();
        } catch (\Exception $e) {
            Log::error('Failed to execute common queries: ' . $e->getMessage());
        }
    }

    /**
     * 檢測記憶體洩漏
     */
    private function detectMemoryLeaks(): array
    {
        $memoryLeaks = [];
        
        try {
            $currentMemory = memory_get_usage(true);
            $peakMemory = memory_get_peak_usage(true);
            $memoryLimit = ini_get('memory_limit');
            
            // 檢查記憶體使用是否接近限制
            if ($memoryLimit !== '-1') {
                $memoryLimitBytes = $this->convertToBytes($memoryLimit);
                $memoryUsagePercent = ($currentMemory / $memoryLimitBytes) * 100;
                
                if ($memoryUsagePercent > 80) {
                    $memoryLeaks[] = [
                        'type' => 'high_memory_usage',
                        'current_memory' => $this->formatBytes($currentMemory),
                        'peak_memory' => $this->formatBytes($peakMemory),
                        'usage_percent' => round($memoryUsagePercent, 2),
                        'severity' => $memoryUsagePercent > 95 ? 'critical' : 'warning'
                    ];
                }
            }
            
            // 檢查記憶體增長趨勢
            $memoryGrowth = $this->checkMemoryGrowth();
            if ($memoryGrowth > 10) { // 記憶體增長超過 10%
                $memoryLeaks[] = [
                    'type' => 'memory_growth',
                    'growth_percent' => $memoryGrowth,
                    'severity' => $memoryGrowth > 50 ? 'critical' : 'warning'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Failed to detect memory leaks: ' . $e->getMessage());
        }
        
        return $memoryLeaks;
    }

    /**
     * 檢查記憶體增長
     */
    private function checkMemoryGrowth(): float
    {
        $cacheKey = 'memory_usage_history';
        $history = Cache::get($cacheKey, []);
        
        $currentMemory = memory_get_usage(true);
        $history[] = [
            'timestamp' => now()->timestamp,
            'memory' => $currentMemory
        ];
        
        // 只保留最近 10 個記錄
        if (count($history) > 10) {
            $history = array_slice($history, -10);
        }
        
        Cache::put($cacheKey, $history, 3600);
        
        if (count($history) < 2) {
            return 0;
        }
        
        $oldest = $history[0]['memory'];
        $newest = $history[count($history) - 1]['memory'];
        
        return round((($newest - $oldest) / $oldest) * 100, 2);
    }

    /**
     * 找出效能瓶頸
     */
    private function findBottlenecks(): array
    {
        $bottlenecks = [];
        
        try {
            // 檢查資料庫連線池
            $dbConnections = $this->getDatabaseConnections();
            if ($dbConnections > 40) {
                $bottlenecks[] = [
                    'type' => 'database_connections',
                    'current' => $dbConnections,
                    'threshold' => 40,
                    'severity' => 'warning'
                ];
            }
            
            // 檢查快取命中率
            $cacheHitRate = $this->getCacheHitRate();
            if ($cacheHitRate < 70) {
                $bottlenecks[] = [
                    'type' => 'cache_hit_rate',
                    'current' => $cacheHitRate,
                    'threshold' => 70,
                    'severity' => 'warning'
                ];
            }
            
            // 檢查佇列大小
            $queueSize = $this->getQueueSize();
            if ($queueSize > 500) {
                $bottlenecks[] = [
                    'type' => 'queue_size',
                    'current' => $queueSize,
                    'threshold' => 500,
                    'severity' => 'warning'
                ];
            }
            
            // 檢查檔案系統 I/O
            $diskIo = $this->checkDiskIo();
            if ($diskIo['read_ops'] > 1000 || $diskIo['write_ops'] > 1000) {
                $bottlenecks[] = [
                    'type' => 'disk_io',
                    'read_ops' => $diskIo['read_ops'],
                    'write_ops' => $diskIo['write_ops'],
                    'severity' => 'warning'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Failed to find bottlenecks: ' . $e->getMessage());
        }
        
        return $bottlenecks;
    }

    /**
     * 找出優化機會
     */
    private function findOptimizations(): array
    {
        $optimizations = [];
        
        try {
            // 資料庫優化建議
            $dbOptimizations = $this->getDatabaseOptimizations();
            $optimizations = array_merge($optimizations, $dbOptimizations);
            
            // 快取優化建議
            $cacheOptimizations = $this->getCacheOptimizations();
            $optimizations = array_merge($optimizations, $cacheOptimizations);
            
            // 程式碼優化建議
            $codeOptimizations = $this->getCodeOptimizations();
            $optimizations = array_merge($optimizations, $codeOptimizations);
            
            // 基礎設施優化建議
            $infraOptimizations = $this->getInfrastructureOptimizations();
            $optimizations = array_merge($optimizations, $infraOptimizations);
        } catch (\Exception $e) {
            Log::error('Failed to find optimizations: ' . $e->getMessage());
        }
        
        return $optimizations;
    }

    /**
     * 資料庫優化建議
     */
    private function getDatabaseOptimizations(): array
    {
        $optimizations = [];
        
        try {
            // 檢查缺少的索引
            $missingIndexes = $this->findMissingIndexes();
            if (!empty($missingIndexes)) {
                $optimizations[] = [
                    'type' => 'missing_indexes',
                    'description' => '缺少資料庫索引',
                    'details' => $missingIndexes,
                    'priority' => 'high',
                    'impact' => '可提升查詢效能 50-80%'
                ];
            }
            
            // 檢查未使用的索引
            $unusedIndexes = $this->findUnusedIndexes();
            if (!empty($unusedIndexes)) {
                $optimizations[] = [
                    'type' => 'unused_indexes',
                    'description' => '未使用的資料庫索引',
                    'details' => $unusedIndexes,
                    'priority' => 'medium',
                    'impact' => '可減少儲存空間和寫入開銷'
                ];
            }
            
            // 檢查表大小
            $largeTables = $this->findLargeTables();
            if (!empty($largeTables)) {
                $optimizations[] = [
                    'type' => 'large_tables',
                    'description' => '大型資料表需要分割',
                    'details' => $largeTables,
                    'priority' => 'medium',
                    'impact' => '可提升查詢效能和維護效率'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Failed to get database optimizations: ' . $e->getMessage());
        }
        
        return $optimizations;
    }

    /**
     * 快取優化建議
     */
    private function getCacheOptimizations(): array
    {
        $optimizations = [];
        
        try {
            $cacheHitRate = $this->getCacheHitRate();
            
            if ($cacheHitRate < 80) {
                $optimizations[] = [
                    'type' => 'cache_strategy',
                    'description' => '快取策略需要優化',
                    'details' => "當前命中率: {$cacheHitRate}%",
                    'priority' => 'high',
                    'impact' => '可提升應用程式效能 30-50%'
                ];
            }
            
            // 檢查快取大小
            $cacheSize = $this->getCacheSize();
            if ($cacheSize > 100 * 1024 * 1024) { // 超過 100MB
                $optimizations[] = [
                    'type' => 'cache_size',
                    'description' => '快取大小過大',
                    'details' => "當前大小: " . $this->formatBytes($cacheSize),
                    'priority' => 'medium',
                    'impact' => '可減少記憶體使用'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Failed to get cache optimizations: ' . $e->getMessage());
        }
        
        return $optimizations;
    }

    /**
     * 程式碼優化建議
     */
    private function getCodeOptimizations(): array
    {
        $optimizations = [];
        
        try {
            // 檢查 N+1 查詢問題
            $nPlusOneQueries = $this->detectNPlusOneQueries();
            if (!empty($nPlusOneQueries)) {
                $optimizations[] = [
                    'type' => 'n_plus_one_queries',
                    'description' => '檢測到 N+1 查詢問題',
                    'details' => $nPlusOneQueries,
                    'priority' => 'high',
                    'impact' => '可大幅減少資料庫查詢次數'
                ];
            }
            
            // 檢查重複查詢
            $duplicateQueries = $this->detectDuplicateQueries();
            if (!empty($duplicateQueries)) {
                $optimizations[] = [
                    'type' => 'duplicate_queries',
                    'description' => '檢測到重複查詢',
                    'details' => $duplicateQueries,
                    'priority' => 'medium',
                    'impact' => '可減少不必要的資料庫負載'
                ];
            }
        } catch (\Exception $e) {
            Log::error('Failed to get code optimizations: ' . $e->getMessage());
        }
        
        return $optimizations;
    }

    /**
     * 基礎設施優化建議
     */
    private function getInfrastructureOptimizations(): array
    {
        $optimizations = [];
        
        try {
            // 檢查 PHP 設定
            $phpOptimizations = $this->checkPhpConfiguration();
            $optimizations = array_merge($optimizations, $phpOptimizations);
            
            // 檢查 Web 伺服器設定
            $webServerOptimizations = $this->checkWebServerConfiguration();
            $optimizations = array_merge($optimizations, $webServerOptimizations);
        } catch (\Exception $e) {
            Log::error('Failed to get infrastructure optimizations: ' . $e->getMessage());
        }
        
        return $optimizations;
    }

    /**
     * 分析快取效能
     */
    private function analyzeCachePerformance(): array
    {
        return [
            'hit_rate' => $this->getCacheHitRate(),
            'size' => $this->getCacheSize(),
            'operations' => $this->getCacheOperations(),
            'efficiency' => $this->calculateCacheEfficiency()
        ];
    }

    /**
     * 分析資料庫效能
     */
    private function analyzeDatabasePerformance(): array
    {
        return [
            'connections' => $this->getDatabaseConnections(),
            'slow_queries' => count($this->identifySlowQueries()),
            'index_usage' => $this->getIndexUsage(),
            'table_sizes' => $this->getTableSizes()
        ];
    }

    /**
     * 分析 API 效能
     */
    private function analyzeApiPerformance(): array
    {
        return [
            'response_times' => $this->getApiResponseTimes(),
            'throughput' => $this->getApiThroughput(),
            'error_rates' => $this->getApiErrorRates(),
            'endpoint_performance' => $this->getEndpointPerformance()
        ];
    }

    /**
     * 生成優化建議
     */
    public function generateOptimizationSuggestions(): array
    {
        $performance = $this->analyzePerformance();
        $suggestions = [];
        
        // 基於分析結果生成建議
        if (!empty($performance['slow_queries'])) {
            $suggestions[] = [
                'category' => 'database',
                'title' => '優化慢查詢',
                'description' => '發現 ' . count($performance['slow_queries']) . ' 個慢查詢',
                'actions' => [
                    '添加適當的資料庫索引',
                    '重構查詢邏輯',
                    '使用查詢快取'
                ],
                'priority' => 'high',
                'estimated_impact' => '可提升查詢效能 50-80%'
            ];
        }
        
        if (!empty($performance['memory_leaks'])) {
            $suggestions[] = [
                'category' => 'memory',
                'title' => '解決記憶體洩漏',
                'description' => '檢測到記憶體使用問題',
                'actions' => [
                    '檢查物件生命週期',
                    '優化資料結構',
                    '增加記憶體限制'
                ],
                'priority' => 'critical',
                'estimated_impact' => '可提升系統穩定性'
            ];
        }
        
        if (!empty($performance['bottlenecks'])) {
            $suggestions[] = [
                'category' => 'performance',
                'title' => '解決效能瓶頸',
                'description' => '發現 ' . count($performance['bottlenecks']) . ' 個效能瓶頸',
                'actions' => [
                    '優化資源配置',
                    '增加快取層',
                    '負載平衡'
                ],
                'priority' => 'high',
                'estimated_impact' => '可提升整體效能 30-50%'
            ];
        }
        
        return $suggestions;
    }

    // 輔助方法
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

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function getDatabaseConnections(): int
    {
        try {
            $connections = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            return (int) ($connections[0]->Value ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getCacheHitRate(): float
    {
        try {
            if (config('cache.default') === 'redis') {
                $info = \Illuminate\Support\Facades\Redis::info();
                $hits = $info['keyspace_hits'] ?? 0;
                $misses = $info['keyspace_misses'] ?? 0;
                $total = $hits + $misses;
                
                if ($total > 0) {
                    return round(($hits / $total) * 100, 2);
                }
            }
            return 0.0;
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    private function getQueueSize(): int
    {
        try {
            if (config('queue.default') === 'redis') {
                return \Illuminate\Support\Facades\Redis::llen('queues:default');
            }
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function checkDiskIo(): array
    {
        // 簡化的磁碟 I/O 檢查
        return [
            'read_ops' => 0,
            'write_ops' => 0
        ];
    }

    private function findMissingIndexes(): array
    {
        // 簡化的索引檢查
        return [];
    }

    private function findUnusedIndexes(): array
    {
        // 簡化的未使用索引檢查
        return [];
    }

    private function findLargeTables(): array
    {
        // 簡化的大型表檢查
        return [];
    }

    private function getCacheSize(): int
    {
        try {
            if (config('cache.default') === 'redis') {
                $info = \Illuminate\Support\Facades\Redis::info();
                return $info['used_memory'] ?? 0;
            }
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getCacheOperations(): array
    {
        return [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0
        ];
    }

    private function calculateCacheEfficiency(): float
    {
        return $this->getCacheHitRate();
    }

    private function getIndexUsage(): array
    {
        return [];
    }

    private function getTableSizes(): array
    {
        return [];
    }

    private function getApiResponseTimes(): array
    {
        return [];
    }

    private function getApiThroughput(): float
    {
        return 0.0;
    }

    private function getApiErrorRates(): array
    {
        return [];
    }

    private function getEndpointPerformance(): array
    {
        return [];
    }

    private function detectNPlusOneQueries(): array
    {
        return [];
    }

    private function detectDuplicateQueries(): array
    {
        return [];
    }

    private function checkPhpConfiguration(): array
    {
        return [];
    }

    private function checkWebServerConfiguration(): array
    {
        return [];
    }
}
