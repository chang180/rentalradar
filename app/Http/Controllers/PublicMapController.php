<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PublicMapController extends Controller
{
    /**
     * 檢查當前 IP 的免費試用可用性
     */
    public function checkAvailability(Request $request): array
    {
        $ip = $request->ip();
        $cacheKey = "public_map_usage_{$ip}";

        // 獲取今日使用記錄
        $usageData = Cache::get($cacheKey, [
            'date' => now()->format('Y-m-d'),
            'used_seconds' => 0,
            'sessions' => [],
        ]);

        // 確保資料結構完整
        $usageData = array_merge([
            'date' => now()->format('Y-m-d'),
            'used_seconds' => 0,
            'sessions' => [],
        ], $usageData);

        // 如果是新的一天，重置使用時間
        if (($usageData['date'] ?? '') !== now()->format('Y-m-d')) {
            $usageData = [
                'date' => now()->format('Y-m-d'),
                'used_seconds' => 0,
                'sessions' => [],
            ];
        }

        // 檢查是否超過每日 30 分鐘限制
        $isAvailable = $usageData['used_seconds'] < 1800; // 1800 秒 = 30 分鐘
        $remainingSeconds = max(0, 1800 - $usageData['used_seconds']);

        return [
            'is_available' => $isAvailable,
            'remaining_seconds' => $remainingSeconds,
            'used_seconds' => $usageData['used_seconds'],
            'daily_limit_seconds' => 1800,
        ];
    }
}
