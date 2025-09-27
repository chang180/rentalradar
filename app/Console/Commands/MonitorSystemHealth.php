<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SystemHealthMonitor;
use App\Services\ErrorDetectionSystem;
use App\Services\AutoRepairSystem;
use Illuminate\Support\Facades\Log;

class MonitorSystemHealth extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'monitor:health 
                            {--auto-repair : 自動修復檢測到的問題}
                            {--send-alerts : 發送警報通知}
                            {--threshold=80 : 健康分數閾值}';

    /**
     * The console command description.
     */
    protected $description = '監控系統健康狀態並執行自動修復';

    private SystemHealthMonitor $healthMonitor;
    private ErrorDetectionSystem $errorDetector;
    private AutoRepairSystem $autoRepair;

    public function __construct(
        SystemHealthMonitor $healthMonitor,
        ErrorDetectionSystem $errorDetector,
        AutoRepairSystem $autoRepair
    ) {
        parent::__construct();
        $this->healthMonitor = $healthMonitor;
        $this->errorDetector = $errorDetector;
        $this->autoRepair = $autoRepair;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('開始監控系統健康狀態...');
        
        try {
            // 獲取系統健康狀態
            $systemHealth = $this->healthMonitor->getSystemHealth();
            
            $this->displayHealthStatus($systemHealth);
            
            // 檢查健康分數
            $threshold = (int) $this->option('threshold');
            if ($systemHealth['health_score'] < $threshold) {
                $this->warn("系統健康分數低於閾值: {$systemHealth['health_score']}% < {$threshold}%");
                
                // 檢測錯誤
                $errors = $this->errorDetector->detectErrors();
                $this->displayErrors($errors);
                
                // 發送警報
                if ($this->option('send-alerts')) {
                    $this->errorDetector->sendAlerts($errors);
                    $this->info('警報已發送');
                }
                
                // 自動修復
                if ($this->option('auto-repair') && !empty($errors)) {
                    $this->info('開始自動修復...');
                    $repairResults = $this->autoRepair->executeAutoRepair($errors);
                    $this->displayRepairResults($repairResults);
                }
            } else {
                $this->info("系統健康狀態良好: {$systemHealth['health_score']}%");
            }
            
            // 記錄監控結果
            $this->logMonitoringResult($systemHealth);
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('監控過程中發生錯誤: ' . $e->getMessage());
            Log::error('System health monitoring failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 顯示健康狀態
     */
    private function displayHealthStatus(array $systemHealth): void
    {
        $this->info('=== 系統健康狀態 ===');
        $this->line("健康分數: {$systemHealth['health_score']}%");
        $this->line("狀態: {$systemHealth['status']}");
        
        $this->info('核心指標:');
        $coreMetrics = $systemHealth['core_metrics'];
        $this->line("  CPU 使用率: {$coreMetrics['cpu_usage']}%");
        $this->line("  記憶體使用率: {$coreMetrics['memory_usage']}%");
        $this->line("  磁碟使用率: {$coreMetrics['disk_usage']}%");
        $this->line("  響應時間: {$coreMetrics['response_time']}ms");
        $this->line("  資料庫連線: {$coreMetrics['database_connections']}");
        $this->line("  佇列大小: {$coreMetrics['queue_size']}");
        
        $this->info('應用程式指標:');
        $appMetrics = $systemHealth['app_metrics'];
        $this->line("  活躍使用者: {$appMetrics['active_users']}");
        $this->line("  API 請求: {$appMetrics['api_requests']}");
        $this->line("  錯誤率: {$appMetrics['error_rate']}%");
        $this->line("  快取命中率: {$appMetrics['cache_hit_rate']}%");
        $this->line("  資料庫查詢: {$appMetrics['database_queries']}");
        
        if (!empty($systemHealth['alerts'])) {
            $this->warn('警報:');
            foreach ($systemHealth['alerts'] as $alert) {
                $this->line("  - {$alert['message']} ({$alert['value']} / {$alert['threshold']})");
            }
        }
    }

    /**
     * 顯示錯誤
     */
    private function displayErrors(array $errors): void
    {
        if (empty($errors)) {
            $this->info('未檢測到錯誤');
            return;
        }
        
        $this->warn('檢測到的錯誤:');
        foreach ($errors as $error) {
            $this->line("  - {$error['message']} ({$error['severity']})");
        }
    }

    /**
     * 顯示修復結果
     */
    private function displayRepairResults(array $repairResults): void
    {
        $this->info('修復結果:');
        foreach ($repairResults as $result) {
            $status = $result['success'] ? '成功' : '失敗';
            $this->line("  - {$result['repair_type']}: {$status} ({$result['execution_time']}ms)");
            if (!$result['success']) {
                $this->line("    錯誤: {$result['message']}");
            }
        }
        
        $successful = count(array_filter($repairResults, fn($r) => $r['success']));
        $total = count($repairResults);
        $this->info("修復成功率: {$successful}/{$total}");
    }

    /**
     * 記錄監控結果
     */
    private function logMonitoringResult(array $systemHealth): void
    {
        $logData = [
            'health_score' => $systemHealth['health_score'],
            'status' => $systemHealth['status'],
            'core_metrics' => $systemHealth['core_metrics'],
            'app_metrics' => $systemHealth['app_metrics'],
            'alerts_count' => count($systemHealth['alerts']),
            'timestamp' => now()->toISOString()
        ];
        
        Log::channel('system')->info('System health monitoring completed', $logData);
    }
}
