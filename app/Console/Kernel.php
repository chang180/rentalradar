<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // 政府資料下載排程
        // 每月 1、11、21 日 02:00 執行
        $schedule->command('government:download --format=csv --parse --save')
            ->monthlyOn(1, '02:00')
            ->monthlyOn(11, '02:00')
            ->monthlyOn(21, '02:00')
            ->name('government-data-download')
            ->withoutOverlapping()
            ->runInBackground();

        // 清理舊檔案排程
        // 每週日 03:00 執行
        $schedule->command('government:download --cleanup')
            ->weeklyOn(0, '03:00')
            ->name('government-data-cleanup')
            ->withoutOverlapping();

        // 地理編碼排程
        // 每日 04:00 執行，處理未編碼的資料
        $schedule->command('properties:geocode --limit=100')
            ->dailyAt('04:00')
            ->name('property-geocoding')
            ->withoutOverlapping();

        // 資料品質檢查排程
        // 每週一 05:00 執行
        $schedule->command('rental:process --validate --notify')
            ->weeklyOn(1, '05:00')
            ->name('data-quality-check')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
