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
