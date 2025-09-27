<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UserBehaviorTrackingService
{
    private const CACHE_KEY = 'user_behavior';

    private const CACHE_TTL = 7200; // 2 hours

    /**
     * 追蹤使用者行為
     */
    public function trackBehavior(array $behaviorData): void
    {
        $behavior = [
            'id' => $this->generateBehaviorId(),
            'user_id' => $behaviorData['user_id'] ?? 'anonymous',
            'session_id' => $behaviorData['session_id'] ?? $this->generateSessionId(),
            'timestamp' => now()->timestamp,
            'action' => $behaviorData['action'] ?? 'unknown',
            'page' => $behaviorData['page'] ?? '/',
            'duration' => $behaviorData['duration'] ?? 0,
            'metadata' => $behaviorData['metadata'] ?? [],
            'ip_address' => $behaviorData['ip_address'] ?? null,
            'user_agent' => $behaviorData['user_agent'] ?? null,
        ];

        // 儲存到快取
        $this->storeInCache($behavior);

        // 記錄到日誌
        $this->logBehavior($behavior);
    }

    /**
     * 獲取使用者行為統計
     */
    public function getUserBehaviorStats(string $timeRange = '24h'): array
    {
        $cacheKey = self::CACHE_KEY.'_stats_'.$timeRange;

        return Cache::remember($cacheKey, 600, function () use ($timeRange) {
            $startTime = $this->getTimeRangeStart($timeRange);
            $behaviors = $this->getBehaviorsFromCache($startTime);

            return [
                'total_sessions' => $this->getUniqueSessions($behaviors),
                'total_users' => $this->getUniqueUsers($behaviors),
                'total_actions' => count($behaviors),
                'avg_session_duration' => $this->getAverageSessionDuration($behaviors),
                'top_actions' => $this->getTopActions($behaviors),
                'top_pages' => $this->getTopPages($behaviors),
                'user_activity' => $this->getUserActivity($behaviors),
                'hourly_activity' => $this->getHourlyActivity($behaviors),
            ];
        });
    }

    /**
     * 獲取使用者行為列表
     */
    public function getBehaviors(array $filters = []): array
    {
        $behaviors = $this->getBehaviorsFromCache();

        // 應用過濾器
        if (isset($filters['user_id'])) {
            $behaviors = array_filter($behaviors, fn ($behavior) => $behavior['user_id'] === $filters['user_id']);
        }

        if (isset($filters['session_id'])) {
            $behaviors = array_filter($behaviors, fn ($behavior) => $behavior['session_id'] === $filters['session_id']);
        }

        if (isset($filters['action'])) {
            $behaviors = array_filter($behaviors, fn ($behavior) => $behavior['action'] === $filters['action']);
        }

        if (isset($filters['since'])) {
            $since = is_string($filters['since']) ? strtotime($filters['since']) : $filters['since'];
            $behaviors = array_filter($behaviors, fn ($behavior) => $behavior['timestamp'] >= $since);
        }

        // 排序
        usort($behaviors, fn ($a, $b) => $b['timestamp'] - $a['timestamp']);

        return $behaviors;
    }

    /**
     * 獲取使用者會話分析
     */
    public function getSessionAnalysis(string $sessionId): array
    {
        $behaviors = $this->getBehaviors(['session_id' => $sessionId]);

        if (empty($behaviors)) {
            return [];
        }

        $sessionStart = min(array_column($behaviors, 'timestamp'));
        $sessionEnd = max(array_column($behaviors, 'timestamp'));
        $sessionDuration = $sessionEnd - $sessionStart;

        return [
            'session_id' => $sessionId,
            'user_id' => $behaviors[0]['user_id'],
            'start_time' => $sessionStart,
            'end_time' => $sessionEnd,
            'duration' => $sessionDuration,
            'action_count' => count($behaviors),
            'unique_pages' => count(array_unique(array_column($behaviors, 'page'))),
            'actions' => $behaviors,
            'page_flow' => $this->getPageFlow($behaviors),
        ];
    }

    /**
     * 清理舊行為資料
     */
    public function cleanupOldBehaviors(int $daysToKeep = 30): int
    {
        $cutoffTime = now()->subDays($daysToKeep)->timestamp;
        $behaviors = $this->getBehaviorsFromCache();

        $originalCount = count($behaviors);
        $behaviors = array_filter($behaviors, fn ($behavior) => $behavior['timestamp'] >= $cutoffTime);

        $this->storeBehaviorsInCache($behaviors);

        return $originalCount - count($behaviors);
    }

    /**
     * 生成行為 ID
     */
    private function generateBehaviorId(): string
    {
        return 'behavior_'.uniqid().'_'.time();
    }

    /**
     * 生成會話 ID
     */
    private function generateSessionId(): string
    {
        return 'session_'.uniqid().'_'.time();
    }

    /**
     * 儲存到快取
     */
    private function storeInCache(array $behavior): void
    {
        $behaviors = $this->getBehaviorsFromCache();
        $behaviors[] = $behavior;

        // 只保留最新的 5000 個行為
        if (count($behaviors) > 5000) {
            $behaviors = array_slice($behaviors, -5000);
        }

        $this->storeBehaviorsInCache($behaviors);
    }

    /**
     * 從快取獲取行為
     */
    private function getBehaviorsFromCache(?int $since = null): array
    {
        $behaviors = Cache::get(self::CACHE_KEY, []);

        if ($since) {
            $behaviors = array_filter($behaviors, fn ($behavior) => $behavior['timestamp'] >= $since);
        }

        return $behaviors;
    }

    /**
     * 儲存行為到快取
     */
    private function storeBehaviorsInCache(array $behaviors): void
    {
        Cache::put(self::CACHE_KEY, $behaviors, self::CACHE_TTL);
    }

    /**
     * 記錄行為到日誌
     */
    private function logBehavior(array $behavior): void
    {
        Log::info('User Behavior Tracked', [
            'user_id' => $behavior['user_id'],
            'action' => $behavior['action'],
            'page' => $behavior['page'],
            'duration' => $behavior['duration'],
        ]);
    }

    /**
     * 獲取時間範圍開始時間
     */
    private function getTimeRangeStart(string $timeRange): int
    {
        switch ($timeRange) {
            case '1h':
                return now()->subHour()->timestamp;
            case '6h':
                return now()->subHours(6)->timestamp;
            case '24h':
                return now()->subDay()->timestamp;
            case '7d':
                return now()->subDays(7)->timestamp;
            default:
                return now()->subDay()->timestamp;
        }
    }

    /**
     * 獲取唯一會話數
     */
    private function getUniqueSessions(array $behaviors): int
    {
        $sessions = array_unique(array_column($behaviors, 'session_id'));

        return count($sessions);
    }

    /**
     * 獲取唯一使用者數
     */
    private function getUniqueUsers(array $behaviors): int
    {
        $users = array_unique(array_column($behaviors, 'user_id'));

        return count($users);
    }

    /**
     * 獲取平均會話時長
     */
    private function getAverageSessionDuration(array $behaviors): float
    {
        $sessions = [];
        foreach ($behaviors as $behavior) {
            $sessionId = $behavior['session_id'];
            if (! isset($sessions[$sessionId])) {
                $sessions[$sessionId] = ['start' => $behavior['timestamp'], 'end' => $behavior['timestamp']];
            } else {
                $sessions[$sessionId]['start'] = min($sessions[$sessionId]['start'], $behavior['timestamp']);
                $sessions[$sessionId]['end'] = max($sessions[$sessionId]['end'], $behavior['timestamp']);
            }
        }

        if (empty($sessions)) {
            return 0;
        }

        $durations = array_map(fn ($session) => $session['end'] - $session['start'], $sessions);

        return array_sum($durations) / count($durations);
    }

    /**
     * 獲取最常見的動作
     */
    private function getTopActions(array $behaviors, int $limit = 10): array
    {
        $actionCounts = [];
        foreach ($behaviors as $behavior) {
            $action = $behavior['action'];
            $actionCounts[$action] = ($actionCounts[$action] ?? 0) + 1;
        }

        arsort($actionCounts);

        return array_slice($actionCounts, 0, $limit, true);
    }

    /**
     * 獲取最常見的頁面
     */
    private function getTopPages(array $behaviors, int $limit = 10): array
    {
        $pageCounts = [];
        foreach ($behaviors as $behavior) {
            $page = $behavior['page'];
            $pageCounts[$page] = ($pageCounts[$page] ?? 0) + 1;
        }

        arsort($pageCounts);

        return array_slice($pageCounts, 0, $limit, true);
    }

    /**
     * 獲取使用者活動
     */
    private function getUserActivity(array $behaviors): array
    {
        $userActivity = [];
        foreach ($behaviors as $behavior) {
            $userId = $behavior['user_id'];
            if (! isset($userActivity[$userId])) {
                $userActivity[$userId] = 0;
            }
            $userActivity[$userId]++;
        }

        arsort($userActivity);

        return $userActivity;
    }

    /**
     * 獲取每小時活動
     */
    private function getHourlyActivity(array $behaviors): array
    {
        $hourlyActivity = [];
        foreach ($behaviors as $behavior) {
            $hour = date('H', $behavior['timestamp']);
            $hourlyActivity[$hour] = ($hourlyActivity[$hour] ?? 0) + 1;
        }

        ksort($hourlyActivity);

        return $hourlyActivity;
    }

    /**
     * 獲取頁面流程
     */
    private function getPageFlow(array $behaviors): array
    {
        $pages = array_column($behaviors, 'page');
        $flow = [];

        for ($i = 0; $i < count($pages) - 1; $i++) {
            $from = $pages[$i];
            $to = $pages[$i + 1];
            $key = $from.' -> '.$to;
            $flow[$key] = ($flow[$key] ?? 0) + 1;
        }

        arsort($flow);

        return $flow;
    }
}
