<?php

namespace App\Support;

class PerformanceMonitor
{
    private float $startedAt;
    private int $startMemory;
    private array $checkpoints = [];

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

    public function summary(array $extra = []): array
    {
        $totalMs = (microtime(true) - $this->startedAt) * 1000;
        $memoryMb = (memory_get_peak_usage(true) - $this->startMemory) / 1048576;

        $baseline = [
            'name' => $this->name,
            'response_time' => round($totalMs, 3),
            'memory_usage' => round(max(0, $memoryMb), 4),
            'checkpoints' => $this->checkpoints,
        ];

        return array_merge($baseline, $extra);
    }
}
