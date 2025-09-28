<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScheduleDataRetention extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'data:retention-schedule 
                            {--frequency=daily : 清理頻率 (hourly, 6hourly, daily, weekly, monthly)}
                            {--dry-run : 模擬執行}';

    /**
     * The console command description.
     */
    protected $description = '排程執行資料保留清理';

    /**
     * Execute the console command.
     */
    public function handle(DataRetentionService $retentionService): int
    {
        $frequency = $this->option('frequency');
        $isDryRun = $this->option('dry-run');

        $this->info("🕐 執行 {$frequency} 資料保留清理...");
        
        if ($isDryRun) {
            $this->warn('⚠️  模擬執行模式');
        }

        try {
            // 根據頻率決定清理策略
            $result = $this->executeRetentionByFrequency($retentionService, $frequency, $isDryRun);
            
            if ($result['success']) {
                $this->info('✅ 資料保留清理完成');
                $this->logRetentionResult($frequency, $result);
            } else {
                $this->error('❌ 資料保留清理失敗');
                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('❌ 執行失敗: ' . $e->getMessage());
            Log::error('資料保留清理失敗', [
                'frequency' => $frequency,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * 根據頻率執行清理
     */
    private function executeRetentionByFrequency(
        DataRetentionService $retentionService, 
        string $frequency, 
        bool $isDryRun
    ): array {
        switch ($frequency) {
            case 'hourly':
                return $this->executeHourlyCleanup($retentionService, $isDryRun);
            case '6hourly':
                return $this->execute6HourlyCleanup($retentionService, $isDryRun);
            case 'daily':
                return $this->executeDailyCleanup($retentionService, $isDryRun);
            case 'weekly':
                return $this->executeWeeklyCleanup($retentionService, $isDryRun);
            case 'monthly':
                return $this->executeMonthlyCleanup($retentionService, $isDryRun);
            default:
                throw new \InvalidArgumentException("不支援的清理頻率: {$frequency}");
        }
    }

    /**
     * 每小時清理
     */
    private function executeHourlyCleanup(DataRetentionService $retentionService, bool $isDryRun): array
    {
        $this->info('⏰ 執行每小時清理...');
        
        if ($isDryRun) {
            return $this->simulateCleanup($retentionService);
        }

        // 每小時清理：只清理最緊急的資料
        $result = $retentionService->cleanupExpiredData();
        
        $this->info("✅ 每小時清理完成");
        $this->line("   - 刪除記錄: " . number_format($result['summary']['total_deleted']));
        $this->line("   - 釋放空間: " . $this->formatBytes($result['summary']['total_space_freed']));

        return $result;
    }

    /**
     * 每 6 小時清理
     */
    private function execute6HourlyCleanup(DataRetentionService $retentionService, bool $isDryRun): array
    {
        $this->info('⏰ 執行每 6 小時清理...');
        
        if ($isDryRun) {
            return $this->simulateCleanup($retentionService);
        }

        // 每 6 小時清理：清理臨時資料
        $result = $retentionService->cleanupExpiredData();
        
        $this->info("✅ 每 6 小時清理完成");
        $this->line("   - 刪除記錄: " . number_format($result['summary']['total_deleted']));
        $this->line("   - 釋放空間: " . $this->formatBytes($result['summary']['total_space_freed']));

        return $result;
    }

    /**
     * 每日清理
     */
    private function executeDailyCleanup(DataRetentionService $retentionService, bool $isDryRun): array
    {
        $this->info('📅 執行每日清理...');
        
        if ($isDryRun) {
            return $this->simulateCleanup($retentionService);
        }

        // 每日清理：快取、會話、臨時檔案
        $result = $retentionService->cleanupExpiredData();
        
        $this->info("✅ 每日清理完成");
        $this->line("   - 刪除記錄: " . number_format($result['summary']['total_deleted']));
        $this->line("   - 釋放空間: " . $this->formatBytes($result['summary']['total_space_freed']));

        return $result;
    }

    /**
     * 每週清理
     */
    private function executeWeeklyCleanup(DataRetentionService $retentionService, bool $isDryRun): array
    {
        $this->info('📅 執行每週清理...');
        
        if ($isDryRun) {
            return $this->simulateCleanup($retentionService);
        }

        // 每週清理：檔案上傳、排程記錄、異常資料
        $result = $retentionService->cleanupExpiredData();
        
        $this->info("✅ 每週清理完成");
        $this->line("   - 刪除記錄: " . number_format($result['summary']['total_deleted']));
        $this->line("   - 歸檔記錄: " . number_format($result['summary']['total_archived']));
        $this->line("   - 釋放空間: " . $this->formatBytes($result['summary']['total_space_freed']));

        return $result;
    }

    /**
     * 每月清理
     */
    private function executeMonthlyCleanup(DataRetentionService $retentionService, bool $isDryRun): array
    {
        $this->info('📅 執行每月清理...');
        
        if ($isDryRun) {
            return $this->simulateCleanup($retentionService);
        }

        // 每月清理：完整清理所有過期資料
        $result = $retentionService->cleanupExpiredData();
        
        $this->info("✅ 每月清理完成");
        $this->line("   - 刪除記錄: " . number_format($result['summary']['total_deleted']));
        $this->line("   - 歸檔記錄: " . number_format($result['summary']['total_archived']));
        $this->line("   - 釋放空間: " . $this->formatBytes($result['summary']['total_space_freed']));

        return $result;
    }

    /**
     * 模擬清理
     */
    private function simulateCleanup(DataRetentionService $retentionService): array
    {
        $this->info('🔍 模擬清理結果：');
        
        $stats = $retentionService->getRetentionStats();
        $totalExpired = 0;
        $totalSpace = 0;

        foreach ($stats as $table => $stat) {
            if ($stat['expired_records'] > 0) {
                $estimatedSize = $stat['expired_records'] * $this->estimateRecordSize($table);
                $totalExpired += $stat['expired_records'];
                $totalSpace += $estimatedSize;

                $this->line("📋 {$table}: " . number_format($stat['expired_records']) . " 筆過期記錄");
            }
        }

        $this->info("📊 模擬清理摘要：");
        $this->line("   - 總過期記錄: " . number_format($totalExpired));
        $this->line("   - 總釋放空間: " . $this->formatBytes($totalSpace));

        return [
            'success' => true,
            'summary' => [
                'total_deleted' => $totalExpired,
                'total_archived' => 0,
                'total_space_freed' => $totalSpace,
            ],
        ];
    }

    /**
     * 記錄清理結果
     */
    private function logRetentionResult(string $frequency, array $result): void
    {
        Log::info("資料保留清理完成", [
            'frequency' => $frequency,
            'deleted_count' => $result['summary']['total_deleted'],
            'archived_count' => $result['summary']['total_archived'],
            'space_freed' => $result['summary']['total_space_freed'],
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * 格式化位元組
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 估算記錄大小
     */
    private function estimateRecordSize(string $table): int
    {
        $sizes = [
            'properties' => 500,
            'predictions' => 200,
            'recommendations' => 300,
            'risk_assessments' => 250,
            'anomalies' => 150,
            'file_uploads' => 100,
            'schedule_executions' => 200,
            'cache' => 50,
            'sessions' => 100,
        ];

        return $sizes[$table] ?? 100;
    }
}
