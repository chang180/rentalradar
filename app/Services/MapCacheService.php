<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class MapCacheService
{
    /**
     * 快取前綴
     */
    private const CACHE_PREFIX = 'map:';

    /**
     * 預設快取時間（秒）
     */
    private const DEFAULT_TTL = 3600; // 1小時

    /**
     * 地圖資料快取時間
     */
    private const MAP_DATA_TTL = 1800; // 30分鐘

    /**
     * 統計資料快取時間
     */
    private const STATISTICS_TTL = 900; // 15分鐘

    /**
     * AI 預測快取時間
     */
    private const AI_PREDICTION_TTL = 600; // 10分鐘

    /**
     * 快取地圖租屋資料
     */
    public function cacheMapRentals(array $filters, array $data, ?int $ttl = null): void
    {
        $key = $this->buildCacheKey('rentals', $filters);
        Cache::put($key, $data, $ttl ?? self::MAP_DATA_TTL);
    }

    /**
     * 取得快取的地圖租屋資料
     */
    public function getCachedMapRentals(array $filters): ?array
    {
        $key = $this->buildCacheKey('rentals', $filters);

        return Cache::get($key);
    }

    /**
     * 快取城市列表
     */
    public function cacheCities(array $cities, ?int $ttl = null): void
    {
        Cache::put(self::CACHE_PREFIX.'cities', $cities, $ttl ?? self::DEFAULT_TTL);
    }

    /**
     * 取得快取的城市列表
     */
    public function getCachedCities(): ?array
    {
        return Cache::get(self::CACHE_PREFIX.'cities');
    }

    /**
     * 快取行政區列表
     */
    public function cacheDistricts(string $city, array $districts, ?int $ttl = null): void
    {
        $key = self::CACHE_PREFIX.'districts:'.$city;
        Cache::put($key, $districts, $ttl ?? self::DEFAULT_TTL);
    }

    /**
     * 取得快取的行政區列表
     */
    public function getCachedDistricts(string $city): ?array
    {
        $key = self::CACHE_PREFIX.'districts:'.$city;

        return Cache::get($key);
    }

    /**
     * 快取統計資料
     */
    public function cacheStatistics(array $filters, array $statistics, ?int $ttl = null): void
    {
        $key = $this->buildCacheKey('statistics', $filters);
        Cache::put($key, $statistics, $ttl ?? self::STATISTICS_TTL);
    }

    /**
     * 取得快取的統計資料
     */
    public function getCachedStatistics(array $filters): ?array
    {
        $key = $this->buildCacheKey('statistics', $filters);

        return Cache::get($key);
    }

    /**
     * 快取 AI 預測結果
     */
    public function cacheAIPrediction(array $input, array $prediction, ?int $ttl = null): void
    {
        $key = $this->buildCacheKey('ai_prediction', $input);
        Cache::put($key, $prediction, $ttl ?? self::AI_PREDICTION_TTL);
    }

    /**
     * 取得快取的 AI 預測結果
     */
    public function getCachedAIPrediction(array $input): ?array
    {
        $key = $this->buildCacheKey('ai_prediction', $input);

        return Cache::get($key);
    }

    /**
     * 快取聚類資料
     */
    public function cacheClusters(array $filters, array $clusters, ?int $ttl = null): void
    {
        $key = $this->buildCacheKey('clusters', $filters);
        Cache::put($key, $clusters, $ttl ?? self::MAP_DATA_TTL);
    }

    /**
     * 取得快取的聚類資料
     */
    public function getCachedClusters(array $filters): ?array
    {
        $key = $this->buildCacheKey('clusters', $filters);

        return Cache::get($key);
    }

    /**
     * 快取熱力圖資料
     */
    public function cacheHeatmap(array $filters, array $heatmap, ?int $ttl = null): void
    {
        $key = $this->buildCacheKey('heatmap', $filters);
        Cache::put($key, $heatmap, $ttl ?? self::MAP_DATA_TTL);
    }

    /**
     * 取得快取的熱力圖資料
     */
    public function getCachedHeatmap(array $filters): ?array
    {
        $key = $this->buildCacheKey('heatmap', $filters);

        return Cache::get($key);
    }

    /**
     * 清除特定城市的所有快取
     */
    public function clearCityCache(string $city): void
    {
        // 使用更簡單的方式：清除所有快取並重新建立
        // 因為 Redis 的 keys 模式匹配在生產環境中可能很慢
        $this->clearAllMapCache();
    }

    /**
     * 清除所有地圖相關快取
     */
    public function clearAllMapCache(): void
    {
        // 使用 Laravel Cache facade 清除快取，更可靠
        Cache::flush();
    }

    /**
     * 使用標記清除相關快取
     */
    public function clearCacheByTags(array $tags): void
    {
        Cache::tags($tags)->flush();
    }

    /**
     * 建立快取鍵值
     */
    private function buildCacheKey(string $type, array $filters): string
    {
        $filterString = '';

        // 排序篩選條件以確保一致性
        ksort($filters);

        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '') {
                // 處理陣列值
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                $filterString .= $key.'='.$value.';';
            }
        }

        return self::CACHE_PREFIX.$type.':'.md5($filterString);
    }

    /**
     * 取得快取統計資訊
     */
    public function getCacheStats(): array
    {
        try {
            $pattern = self::CACHE_PREFIX.'*';
            $keys = Redis::keys($pattern);

            $stats = [
                'total_keys' => count($keys),
                'memory_usage' => 0,
                'key_types' => [],
            ];

            foreach ($keys as $key) {
                try {
                    $memory = Redis::memory('usage', $key);
                    $stats['memory_usage'] += $memory;

                    // 統計鍵值類型
                    $keyParts = explode(':', $key);
                    $type = $keyParts[1] ?? 'unknown';
                    $stats['key_types'][$type] = ($stats['key_types'][$type] ?? 0) + 1;
                } catch (\Exception $e) {
                    // 忽略單個鍵值的錯誤
                    continue;
                }
            }

            return $stats;
        } catch (\Exception $e) {
            // 如果無法取得統計資訊，返回基本資訊
            return [
                'total_keys' => 0,
                'memory_usage' => 0,
                'key_types' => [],
                'error' => 'Unable to fetch cache statistics',
            ];
        }
    }

    /**
     * 檢查 Redis 連接狀態
     */
    public function isConnected(): bool
    {
        try {
            Redis::ping();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
