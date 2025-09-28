<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestSchedule extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'schedule:test 
                            {--show : 顯示所有排程任務}
                            {--run : 執行排程測試}';

    /**
     * The console command description.
     */
    protected $description = '測試 Hostinger 排程設定';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('show')) {
            return $this->showSchedules();
        }

        if ($this->option('run')) {
            return $this->runScheduleTest();
        }

        $this->info('🧪 排程測試工具');
        $this->newLine();
        $this->line('使用方法:');
        $this->line('  php artisan schedule:test --show    # 顯示所有排程任務');
        $this->line('  php artisan schedule:test --run      # 執行排程測試');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * 顯示所有排程任務
     */
    private function showSchedules(): int
    {
        $this->info('📋 所有排程任務:');
        $this->newLine();

        $schedules = [
            [
                '任務' => 'monitor:health --send-alerts',
                '頻率' => '每 5 分鐘',
                '說明' => '監控系統健康狀態',
            ],
            [
                '任務' => 'monitor:health --auto-repair',
                '頻率' => '每 30 分鐘',
                '說明' => '自動修復系統問題',
            ],
            [
                '任務' => 'data:update --geocode --limit=1000',
                '頻率' => '每日 02:00',
                '說明' => '每日資料更新',
            ],
            [
                '任務' => 'data:update --force --geocode --limit=5000',
                '頻率' => '每週日 03:00',
                '說明' => '每週完整資料更新',
            ],
            [
                '任務' => 'data:update --force --geocode --limit=10000',
                '頻率' => '每月 1 號 04:00',
                '說明' => '每月深度維護',
            ],
            [
                '任務' => 'data:retention-schedule --frequency=hourly',
                '頻率' => '每小時',
                '說明' => '每小時清理緊急資料',
            ],
            [
                '任務' => 'data:retention-schedule --frequency=6hourly',
                '頻率' => '每 6 小時',
                '說明' => '清理臨時資料',
            ],
            [
                '任務' => 'data:retention-schedule --frequency=daily',
                '頻率' => '每日 01:00',
                '說明' => '每日清理快取和會話',
            ],
            [
                '任務' => 'data:retention-schedule --frequency=weekly',
                '頻率' => '每週日 01:30',
                '說明' => '每週清理檔案和排程記錄',
            ],
            [
                '任務' => 'data:retention-schedule --frequency=monthly',
                '頻率' => '每月 1 號 02:00',
                '說明' => '每月完整資料清理',
            ],
            [
                '任務' => 'data:cleanup --stats',
                '頻率' => '每週三 03:00',
                '說明' => '顯示資料保留統計',
            ],
        ];

        $this->table(['任務', '頻率', '說明'], $schedules);

        $this->newLine();
        $this->info('💡 提示:');
        $this->line('  - 所有任務都會在 Hostinger 每分鐘執行時自動檢查');
        $this->line('  - 使用 withoutOverlapping() 防止任務重疊');
        $this->line('  - 使用 onOneServer() 確保單一執行');

        return self::SUCCESS;
    }

    /**
     * 執行排程測試
     */
    private function runScheduleTest(): int
    {
        $this->info('🧪 執行排程測試...');
        $this->newLine();

        // 測試當前時間
        $now = now();
        $this->line("⏰ 當前時間: {$now->format('Y-m-d H:i:s')}");
        $this->line("📅 星期: {$now->format('l')} (週{$now->dayOfWeek})");
        $this->line("📆 日期: {$now->format('j')} 號");
        $this->newLine();

        // 測試各種時間條件
        $this->info('🔍 時間條件測試:');
        
        $tests = [
            '每小時執行' => $now->minute === 0,
            '每 6 小時執行' => $now->hour % 6 === 0 && $now->minute === 0,
            '每日 01:00 執行' => $now->hour === 1 && $now->minute === 0,
            '每日 02:00 執行' => $now->hour === 2 && $now->minute === 0,
            '每週日 01:30 執行' => $now->dayOfWeek === 0 && $now->hour === 1 && $now->minute === 30,
            '每週日 03:00 執行' => $now->dayOfWeek === 0 && $now->hour === 3 && $now->minute === 0,
            '每週三 03:00 執行' => $now->dayOfWeek === 3 && $now->hour === 3 && $now->minute === 0,
            '每月 1 號 02:00 執行' => $now->day === 1 && $now->hour === 2 && $now->minute === 0,
            '每月 1 號 04:00 執行' => $now->day === 1 && $now->hour === 4 && $now->minute === 0,
        ];

        foreach ($tests as $test => $result) {
            $status = $result ? '✅ 會執行' : '❌ 不會執行';
            $this->line("  {$test}: {$status}");
        }

        $this->newLine();
        $this->info('📊 測試結果:');
        
        $executingTasks = array_filter($tests);
        $this->line("  - 當前會執行的任務: " . count($executingTasks) . " 個");
        
        if (!empty($executingTasks)) {
            $this->line("  - 執行中的任務:");
            foreach (array_keys($executingTasks) as $task) {
                $this->line("    • {$task}");
            }
        }

        // 記錄測試結果
        Log::info('排程測試完成', [
            'current_time' => $now->toISOString(),
            'executing_tasks' => array_keys($executingTasks),
        ]);

        $this->newLine();
        $this->info('✅ 排程測試完成！');

        return self::SUCCESS;
    }
}
