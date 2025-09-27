<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IpRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $cacheKey = "public_map_usage_{$ip}";
        $sessionKey = "public_map_session_{$ip}";

        $now = now();

        // 獲取今日使用記錄
        $usageData = Cache::get($cacheKey, [
            'date' => $now->format('Y-m-d'),
            'used_seconds' => 0,
            'sessions' => [],
        ]);

        // 確保資料結構完整，防止舊版本快取資料缺少鍵值
        $usageData = array_merge([
            'date' => $now->format('Y-m-d'),
            'used_seconds' => 0,
            'sessions' => [],
        ], $usageData);

        // 如果是新的一天，重置使用時間
        if (($usageData['date'] ?? '') !== $now->format('Y-m-d')) {
            $usageData = [
                'date' => $now->format('Y-m-d'),
                'used_seconds' => 0,
                'sessions' => [],
            ];
        }

        // 獲取當前會話
        $currentSession = Cache::get($sessionKey, [
            'start_time' => null,
            'last_activity' => null,
        ]);

        // 如果是新的會話或會話已過期（超過 30 分鐘無活動）
        $sessionExpired = false;
        if ($currentSession['last_activity']) {
            $inactiveTime = $now->diffInSeconds($currentSession['last_activity']);
            if ($inactiveTime > 1800) { // 30 分鐘無活動視為會話過期
                $sessionExpired = true;
            }
        }

        if ($currentSession['start_time'] === null || $sessionExpired) {
            // 如果有舊會話，計算並記錄使用時間
            if ($currentSession['start_time'] && $currentSession['last_activity']) {
                $sessionDuration = $currentSession['start_time']->diffInSeconds($currentSession['last_activity']);
                $usageData['used_seconds'] += $sessionDuration;
                $usageData['sessions'][] = [
                    'start_time' => $currentSession['start_time']->toISOString(),
                    'end_time' => $currentSession['last_activity']->toISOString(),
                    'duration' => $sessionDuration,
                ];
            }

            // 檢查是否超過每日 30 分鐘限制
            if ($usageData['used_seconds'] >= 1800) {
                return redirect('/')->with('error', '您今日的免費試用時間已用完（30分鐘），請註冊帳號以獲得完整功能。');
            }

            // 開始新會話
            $currentSession = [
                'start_time' => $now,
                'last_activity' => $now,
            ];
        } else {
            // 更新最後活動時間
            $currentSession['last_activity'] = $now;
        }

        // 計算剩餘時間
        $currentSessionDuration = $currentSession['start_time']->diffInSeconds($now);
        $totalUsedSeconds = $usageData['used_seconds'] + $currentSessionDuration;
        $actualRemainingSeconds = max(0, 1800 - $totalUsedSeconds);

        // 如果當前會話已經用完剩餘時間
        if ($actualRemainingSeconds <= 0) {
            // 記錄當前會話的使用時間
            $sessionDuration = min($currentSessionDuration, 1800 - $usageData['used_seconds']);
            $usageData['used_seconds'] += $sessionDuration;
            $usageData['sessions'][] = [
                'start_time' => $currentSession['start_time']->toISOString(),
                'end_time' => $now->toISOString(),
                'duration' => $sessionDuration,
            ];

            Cache::put($cacheKey, $usageData, now()->endOfDay());
            Cache::forget($sessionKey);

            return redirect('/')->with('error', '您今日的免費試用時間已用完（30分鐘），請註冊帳號以獲得完整功能。');
        }

        // 儲存資料
        Cache::put($cacheKey, $usageData, now()->endOfDay());
        Cache::put($sessionKey, $currentSession, now()->addHours(2)); // 會話快取 2 小時

        // 將使用資訊傳遞給控制器
        $request->merge([
            'ip_usage_data' => [
                'used_seconds' => $totalUsedSeconds,
                'current_session_start' => $currentSession['start_time']->toISOString(),
                'sessions' => $usageData['sessions'],
            ],
            'remaining_seconds' => $actualRemainingSeconds,
        ]);

        return $next($request);
    }
}
