<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| 系統監控與維護排程任務
|
*/

// 每 5 分鐘監控系統健康狀態
Schedule::command('monitor:health --send-alerts')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// 每 30 分鐘執行自動修復
Schedule::command('monitor:health --auto-repair')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// 每日凌晨 2 點執行資料更新
Schedule::command('data:update --geocode --limit=1000')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground();

// 每週日凌晨 3 點執行完整資料更新
Schedule::command('data:update --force --geocode --limit=5000')
    ->weeklyOn(0, '03:00')
    ->withoutOverlapping()
    ->runInBackground();

// 每月 1 號凌晨 4 點執行深度維護
Schedule::command('data:update --force --geocode --limit=10000')
    ->monthlyOn(1, '04:00')
    ->withoutOverlapping()
    ->runInBackground();

/*
|--------------------------------------------------------------------------
| 資料保留與清理排程任務
|--------------------------------------------------------------------------
|
| 自動清理過期資料，執行資料保留政策
| 適用於 Hostinger 每分鐘執行的排程系統
|
*/

// 每日清理快取和會話資料 (凌晨 1 點)
Schedule::command('data:retention-schedule --frequency=daily')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onOneServer();

// 每週清理檔案和排程記錄 (週日凌晨 1:30)
Schedule::command('data:retention-schedule --frequency=weekly')
    ->weeklyOn(0, '01:30')
    ->withoutOverlapping()
    ->runInBackground()
    ->onOneServer();

// 每月完整資料清理 (每月 1 號凌晨 2 點)
Schedule::command('data:retention-schedule --frequency=monthly')
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onOneServer();

// 每週三顯示資料保留統計 (凌晨 3 點)
Schedule::command('data:cleanup --stats')
    ->weeklyOn(3, '03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->onOneServer();

// 每小時檢查資料保留狀態 (每小時的 0 分)
Schedule::command('data:retention-schedule --frequency=hourly')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->onOneServer();

// 每 6 小時清理臨時資料 (每 6 小時的 0 分)
Schedule::command('data:retention-schedule --frequency=6hourly')
    ->everySixHours()
    ->withoutOverlapping()
    ->runInBackground()
    ->onOneServer();
