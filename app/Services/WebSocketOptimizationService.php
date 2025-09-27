<?php

namespace App\Services;

use App\Events\MapDataUpdated;
use App\Events\RealTimeNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebSocketOptimizationService
{
    private const THROTTLE_CACHE_PREFIX = 'ws_throttle_';
    private const DATA_CACHE_PREFIX = 'ws_data_';
    private const DEFAULT_THROTTLE_SECONDS = 1;
    private const MAX_DATA_SIZE = 1048576; // 1MB

    public function broadcastMapDataOptimized(array $data, string $type, ?string $cacheKey = null): bool
    {
        try {
            // 資料大小檢查
            if ($this->isDataTooLarge($data)) {
                Log::warning('WebSocket data too large, compressing', ['size' => strlen(serialize($data))]);
                $data = $this->compressData($data);
            }

            // 節流控制
            if ($this->isThrottled($type)) {
                Log::info('WebSocket broadcast throttled', ['type' => $type]);
                return false;
            }

            // 快取重複資料檢查
            if ($cacheKey && $this->isDuplicateData($cacheKey, $data)) {
                Log::info('WebSocket broadcast skipped - duplicate data', ['cache_key' => $cacheKey]);
                return false;
            }

            // 執行廣播
            broadcast(new MapDataUpdated($data, $type));

            // 設定節流和快取
            $this->setThrottle($type);
            if ($cacheKey) {
                $this->cacheData($cacheKey, $data);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('WebSocket broadcast failed', [
                'error' => $e->getMessage(),
                'type' => $type,
                'data_size' => strlen(serialize($data))
            ]);
            return false;
        }
    }

    public function broadcastNotificationOptimized(
        string $message,
        string $type = 'info',
        ?array $data = null,
        ?string $userId = null
    ): bool {
        try {
            // 重複通知檢查
            $notificationHash = $this->generateNotificationHash($message, $type, $userId);
            if ($this->isDuplicateNotification($notificationHash)) {
                Log::info('Duplicate notification skipped', ['hash' => $notificationHash]);
                return false;
            }

            // 通知節流
            if ($userId && $this->isUserNotificationThrottled($userId)) {
                Log::info('User notification throttled', ['user_id' => $userId]);
                return false;
            }

            // 執行廣播
            broadcast(new RealTimeNotification($message, $type, $data, $userId));

            // 設定節流和快取
            $this->cacheNotification($notificationHash);
            if ($userId) {
                $this->setUserNotificationThrottle($userId);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('WebSocket notification failed', [
                'error' => $e->getMessage(),
                'message' => $message,
                'user_id' => $userId
            ]);
            return false;
        }
    }

    public function batchBroadcast(array $events): array
    {
        $results = [];
        $batchStartTime = microtime(true);

        foreach ($events as $index => $event) {
            $startTime = microtime(true);

            if (isset($event['type']) && $event['type'] === 'map_data') {
                $success = $this->broadcastMapDataOptimized(
                    $event['data'],
                    $event['map_type'] ?? 'properties',
                    $event['cache_key'] ?? null
                );
            } elseif (isset($event['type']) && $event['type'] === 'notification') {
                $success = $this->broadcastNotificationOptimized(
                    $event['message'],
                    $event['notification_type'] ?? 'info',
                    $event['data'] ?? null,
                    $event['user_id'] ?? null
                );
            } else {
                $success = false;
            }

            $duration = (microtime(true) - $startTime) * 1000;

            $results[] = [
                'index' => $index,
                'success' => $success,
                'duration_ms' => $duration
            ];
        }

        $totalDuration = (microtime(true) - $batchStartTime) * 1000;

        return [
            'total_events' => count($events),
            'successful' => array_sum(array_column($results, 'success')),
            'total_duration_ms' => $totalDuration,
            'average_duration_ms' => $totalDuration / count($events),
            'results' => $results
        ];
    }

    private function isDataTooLarge(array $data): bool
    {
        return strlen(serialize($data)) > self::MAX_DATA_SIZE;
    }

    private function compressData(array $data): array
    {
        // 簡化大型資料集
        if (isset($data['rentals']) && count($data['rentals']) > 100) {
            $data['rentals'] = array_slice($data['rentals'], 0, 100);
            $data['_compressed'] = true;
            $data['_original_count'] = count($data['rentals']);
        }

        if (isset($data['clusters']) && count($data['clusters']) > 50) {
            $data['clusters'] = array_slice($data['clusters'], 0, 50);
            $data['_compressed'] = true;
        }

        return $data;
    }

    private function isThrottled(string $type): bool
    {
        return Cache::has(self::THROTTLE_CACHE_PREFIX . $type);
    }

    private function setThrottle(string $type): void
    {
        Cache::put(
            self::THROTTLE_CACHE_PREFIX . $type,
            true,
            self::DEFAULT_THROTTLE_SECONDS
        );
    }

    private function isDuplicateData(string $cacheKey, array $data): bool
    {
        $cachedHash = Cache::get(self::DATA_CACHE_PREFIX . $cacheKey);
        $currentHash = md5(serialize($data));

        return $cachedHash === $currentHash;
    }

    private function cacheData(string $cacheKey, array $data): void
    {
        $hash = md5(serialize($data));
        Cache::put(self::DATA_CACHE_PREFIX . $cacheKey, $hash, 300); // 5分鐘
    }

    private function generateNotificationHash(string $message, string $type, ?string $userId): string
    {
        return md5($message . $type . ($userId ?? 'global'));
    }

    private function isDuplicateNotification(string $hash): bool
    {
        return Cache::has('notification_' . $hash);
    }

    private function cacheNotification(string $hash): void
    {
        Cache::put('notification_' . $hash, true, 60); // 1分鐘
    }

    private function isUserNotificationThrottled(string $userId): bool
    {
        return Cache::has('user_notification_throttle_' . $userId);
    }

    private function setUserNotificationThrottle(string $userId): void
    {
        Cache::put('user_notification_throttle_' . $userId, true, 5); // 5秒
    }

    public function getOptimizationStats(): array
    {
        $cacheKeys = [
            'throttle_hits' => 0,
            'duplicate_data_hits' => 0,
            'duplicate_notification_hits' => 0,
            'compression_applied' => 0,
        ];

        // 這裡可以實現更詳細的統計邏輯
        return [
            'optimization_active' => true,
            'throttle_seconds' => self::DEFAULT_THROTTLE_SECONDS,
            'max_data_size_mb' => self::MAX_DATA_SIZE / 1024 / 1024,
            'stats' => $cacheKeys,
            'cache_status' => Cache::getStore() !== null ? 'active' : 'inactive',
        ];
    }
}