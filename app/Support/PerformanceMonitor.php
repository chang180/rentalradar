<?php

namespace App\Support;

class PerformanceMonitor
{
    private float $startedAt;
    private int $startMemory;
    private array $checkpoints = [];
    private array $modelMetrics = [];
    private array $warnings = [];

    private function __construct(private readonly string $name)
    {
        $this->startedAt = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    public static function start(string $name = 'performance'): self
    {
        return new self($name);
    }

    public function mark(string $label): void
    {
        $this->checkpoints[] = [
            'label' => $label,
            'elapsed_ms' => round((microtime(true) - $this->startedAt) * 1000, 3),
            'memory_mb' => round((memory_get_usage(true) - $this->startMemory) / 1048576, 4),
        ];
    }

    /**
     * Measure model inference and record metrics.
     */
    public function trackModel(string $modelName, callable $callback, array $options = []): mixed
    {
        $thresholdMs = $options['threshold_ms'] ?? 250.0;
        $start = microtime(true);
        $startMemory = memory_get_usage(true);

        $result = $callback();

        $durationMs = (microtime(true) - $start) * 1000;
        $memoryDeltaMb = (memory_get_usage(true) - $startMemory) / 1048576;

        $metric = [
            'duration_ms' => round($durationMs, 3),
            'memory_mb' => round(max(0, $memoryDeltaMb), 4),
            'timestamp_ms' => round((microtime(true) - $this->startedAt) * 1000, 3),
        ];

        $this->modelMetrics[$modelName] ??= [];
        $this->modelMetrics[$modelName][] = $metric;

        if ($durationMs > $thresholdMs) {
            $this->warnings[] = [
                'type' => 'model_inference',
                'model' => $modelName,
                'duration_ms' => round($durationMs, 3),
                'threshold_ms' => $thresholdMs,
            ];
        }

        return $result;
    }

    public function addWarning(string $message, array $context = []): void
    {
        $this->warnings[] = array_merge([
            'type' => 'custom',
            'message' => $message,
        ], $context);
    }

    public function summary(array $extra = []): array
    {
        $totalMs = (microtime(true) - $this->startedAt) * 1000;
        $memoryMb = (memory_get_peak_usage(true) - $this->startMemory) / 1048576;

        $baseline = [
            'name' => $this->name,
            'response_time' => round($totalMs, 3),
            'memory_usage' => round(max(0, $memoryMb), 4),
            'checkpoints' => $this->checkpoints,
            'models' => $this->modelMetrics,
            'warnings' => $this->warnings,
        ];

        return array_merge($baseline, $extra);
    }

    /**
     * 獲取當前效能指標
     */
    public function getCurrentMetrics(): array
    {
        return [
            'timestamp' => now()->timestamp,
            'responseTime' => round((microtime(true) - $this->startedAt) * 1000, 2),
            'memoryUsage' => round(memory_get_usage(true) / 1048576, 2),
            'queryCount' => $this->getQueryCount(),
            'cacheHitRate' => $this->getCacheHitRate(),
            'activeConnections' => $this->getActiveConnections(),
            'errorRate' => $this->getErrorRate(),
            'throughput' => $this->getThroughput(),
        ];
    }

    private function getCpuUsage(): float
    {
        // 在 Windows 上使用替代方法
        if (function_exists('sys_getloadavg')) {
            return sys_getloadavg()[0] ?? 0;
        }
        
        // Windows 替代方案：返回 0 或使用其他方法
        return 0.0;
    }

    /**
     * 獲取查詢次數（模擬）
     */
    private function getQueryCount(): int
    {
        // 模擬資料庫查詢次數
        return rand(5, 50);
    }

    /**
     * 獲取快取命中率（模擬）
     */
    private function getCacheHitRate(): float
    {
        // 模擬快取命中率 (70-95%)
        return round(rand(70, 95), 1);
    }

    /**
     * 獲取活躍連接數（模擬）
     */
    private function getActiveConnections(): int
    {
        // 模擬活躍連接數
        return rand(10, 100);
    }

    /**
     * 獲取錯誤率（模擬）
     */
    private function getErrorRate(): float
    {
        // 模擬錯誤率 (0-5%)
        return round(rand(0, 5), 2);
    }

    /**
     * 獲取吞吐量（模擬）
     */
    private function getThroughput(): int
    {
        // 模擬吞吐量 (100-1000 req/min)
        return rand(100, 1000);
    }

    /**
     * 獲取指定時間範圍的效能指標
     */
    public function getMetrics(string $timeRange = '24h'): array
    {
        // 簡化實現，返回當前指標
        return $this->getCurrentMetrics();
    }
}
