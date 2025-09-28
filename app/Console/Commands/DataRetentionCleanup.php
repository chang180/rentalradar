<?php

namespace App\Console\Commands;

use App\Services\DataRetentionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DataRetentionCleanup extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'data:cleanup 
                            {--dry-run : 模擬執行，不實際刪除資料}
                            {--force : 強制執行，跳過確認}
                            {--table= : 只清理指定資料表}
                            {--stats : 只顯示統計資訊}';

    /**
     * The console command description.
     */
    protected $description = '清理過期的資料，執行資料保留政策';

    /**
     * Execute the console command.
     */
    public function handle(DataRetentionService $retentionService): int
    {
        $this->info('🧹 開始執行資料清理...');
        $this->newLine();

        // 顯示統計資訊
        if ($this->option('stats')) {
            return $this->showStats($retentionService);
        }

        // 檢查是否為模擬執行
        if ($this->option('dry-run')) {
            $this->warn('⚠️  模擬執行模式 - 不會實際刪除資料');
            return $this->simulateCleanup($retentionService);
        }

        // 確認執行
        if (!$this->option('force')) {
            if (!$this->confirm('確定要執行資料清理嗎？這將刪除過期的資料。')) {
                $this->info('❌ 取消執行');
                return self::SUCCESS;
            }
        }

        // 執行清理
        $this->info('🚀 開始清理資料...');
        $result = $retentionService->cleanupExpiredData();

        if ($result['success']) {
            $this->displayResults($result);
            $this->info('✅ 資料清理完成！');
        } else {
            $this->error('❌ 資料清理失敗');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * 顯示統計資訊
     */
    private function showStats(DataRetentionService $retentionService): int
    {
        $this->info('📊 資料保留統計資訊');
        $this->newLine();

        $stats = $retentionService->getRetentionStats();
        $dbStats = $retentionService->getDatabaseStats();

        $headers = ['資料表', '總記錄數', '過期記錄數', '保留天數', '優先級', '歸檔'];
        $rows = [];

        foreach ($stats as $table => $stat) {
            $rows[] = [
                $table,
                number_format($stat['total_records']),
                number_format($stat['expired_records']),
                $stat['retention_days'] . ' 天',
                $stat['priority'],
                $stat['archive_before_delete'] ? '是' : '否',
            ];
        }

        $this->table($headers, $rows);

        // 顯示資料庫大小統計
        $this->newLine();
        $this->info('💾 資料庫大小統計');
        $this->newLine();

        $sizeHeaders = ['資料表', '記錄數', '估算大小'];
        $sizeRows = [];

        foreach ($dbStats as $table => $stat) {
            $sizeRows[] = [
                $table,
                number_format($stat['record_count']),
                $this->formatBytes($stat['estimated_size']),
            ];
        }

        $this->table($sizeHeaders, $sizeRows);

        return self::SUCCESS;
    }

    /**
     * 模擬清理
     */
    private function simulateCleanup(DataRetentionService $retentionService): int
    {
        $this->info('🔍 模擬清理結果：');
        $this->newLine();

        $stats = $retentionService->getRetentionStats();
        $totalExpired = 0;
        $totalSpace = 0;

        foreach ($stats as $table => $stat) {
            if ($stat['expired_records'] > 0) {
                $estimatedSize = $stat['expired_records'] * $this->estimateRecordSize($table);
                $totalExpired += $stat['expired_records'];
                $totalSpace += $estimatedSize;

                $this->line("📋 {$table}:");
                $this->line("   - 過期記錄: " . number_format($stat['expired_records']));
                $this->line("   - 估算大小: " . $this->formatBytes($estimatedSize));
                $this->line("   - 保留天數: {$stat['retention_days']} 天");
                $this->newLine();
            }
        }

        $this->info('📊 模擬清理摘要：');
        $this->line("   - 總過期記錄: " . number_format($totalExpired));
        $this->line("   - 總釋放空間: " . $this->formatBytes($totalSpace));

        return self::SUCCESS;
    }

    /**
     * 顯示清理結果
     */
    private function displayResults(array $result): void
    {
        $summary = $result['summary'];
        
        $this->newLine();
        $this->info('📊 清理結果摘要：');
        $this->line("   - 刪除記錄: " . number_format($summary['total_deleted']));
        $this->line("   - 歸檔記錄: " . number_format($summary['total_archived']));
        $this->line("   - 釋放空間: " . $this->formatBytes($summary['total_space_freed']));

        $this->newLine();
        $this->info('📋 詳細結果：');

        foreach ($result['details'] as $table => $detail) {
            if (isset($detail['success']) && $detail['success']) {
                $this->line("✅ {$table}:");
                $this->line("   - 刪除: " . number_format($detail['deleted_count']));
                $this->line("   - 歸檔: " . number_format($detail['archived_count']));
                $this->line("   - 空間: " . $this->formatBytes($detail['space_freed']));
            } else {
                $this->line("❌ {$table}: " . ($detail['error'] ?? '未知錯誤'));
            }
        }
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
