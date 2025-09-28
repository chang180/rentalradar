<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IntelligentCacheService
{
    /**
     * 快取層級定義
     */
    private const CACHE_LAYERS = [
        'hot' => [
            'store' => 'redis',
            'ttl' => 3600, // 1小時
            'description' => '熱門行政區資料',
        ],
        'warm' => [
            'store' => 'redis', 
            'ttl' => 1800, // 30分鐘
            'description' => '一般行政區資料',
        ],
        'cold' => [
            'store' => 'database',
            'ttl' => 7200, // 2小時
            'description' => '冷門行政區資料',
        ],
        'temp' => [
            'store' => 'array',
            'ttl' => 300, // 5分鐘
            'description' => '臨時計算結果',
        ],
    ];

    /**
     * 熱門行政區列表（基於查詢頻率）
     */
    private const HOT_DISTRICTS = [
        '台北市' => ['大安區', '信義區', '松山區', '中山區'],
        '新北市' => ['板橋區', '新店區', '中和區', '永和區'],
        '台中市' => ['西屯區', '北屯區', '南屯區'],
        '高雄市' => ['左營區', '三民區', '前金區'],
    ];

    /**
     * 根據行政區熱門程度選擇快取層級
     */
    public function getCacheLayer(string $city, string $district): string
    {
        // 檢查是否為熱門行政區
        if (isset(self::HOT_DISTRICTS[$city]) && 
            in_array($district, self::HOT_DISTRICTS[$city])) {
            return 'hot';
        }

        // 檢查是否為一般行政區（有資料但查詢頻率中等）
        if ($this->hasRecentData($city, $district)) {
            return 'warm';
        }

        // 其他為冷門行政區
        return 'cold';
    }

    /**
     * 智能快取取得
     */
    public function get(string $key, string $city, string $district, callable $callback = null)
    {
        $layer = $this->getCacheLayer($city, $district);
        $store = self::CACHE_LAYERS[$layer]['store'];
        $ttl = self::CACHE_LAYERS[$layer]['ttl'];
        
        $cacheKey = $this->buildCacheKey($key, $city, $district, $layer);

        // 嘗試從指定層級取得快取
        $cached = Cache::store($store)->get($cacheKey);
        if ($cached !== null) {
            Log::debug("Cache hit", [
                'key' => $cacheKey,
                'layer' => $layer,
                'store' => $store
            ]);
            return $cached;
        }

        // 快取未命中，執行回調函數
        if ($callback) {
            $data = $callback();
            
            // 儲存到對應層級的快取
            Cache::store($store)->put($cacheKey, $data, $ttl);
            
            // 如果是熱門資料，同時更新到上層快取
            if ($layer === 'hot') {
                $this->promoteToHotCache($cacheKey, $data, $city, $district);
            }
            
            Log::debug("Cache miss, data cached", [
                'key' => $cacheKey,
                'layer' => $layer,
                'store' => $store
            ]);
            
            return $data;
        }

        return null;
    }

    /**
     * 智能快取儲存
     */
    public function put(string $key, $data, string $city, string $district, ?int $ttl = null): void
    {
        $layer = $this->getCacheLayer($city, $district);
        $store = self::CACHE_LAYERS[$layer]['store'];
        $defaultTtl = self::CACHE_LAYERS[$layer]['ttl'];
        
        $cacheKey = $this->buildCacheKey($key, $city, $district, $layer);
        
        Cache::store($store)->put($cacheKey, $data, $ttl ?? $defaultTtl);
        
        Log::debug("Data cached", [
            'key' => $cacheKey,
            'layer' => $layer,
            'store' => $store,
            'ttl' => $ttl ?? $defaultTtl
        ]);
    }

    /**
     * 清除特定行政區的快取
     */
    public function clearDistrictCache(string $city, string $district): void
    {
        $layers = ['hot', 'warm', 'cold'];
        
        foreach ($layers as $layer) {
            $store = self::CACHE_LAYERS[$layer]['store'];
            $cacheKey = $this->buildCacheKey('*', $city, $district, $layer);
            
            // 清除該行政區的所有快取
            $this->clearCacheByPattern($store, $cacheKey);
        }
        
        Log::info("District cache cleared", [
            'city' => $city,
            'district' => $district
        ]);
    }

    /**
     * 清除城市級別的快取
     */
    public function clearCityCache(string $city): void
    {
        $layers = ['hot', 'warm', 'cold'];
        
        foreach ($layers as $layer) {
            $store = self::CACHE_LAYERS[$layer]['store'];
            $cacheKey = $this->buildCacheKey('*', $city, '*', $layer);
            
            $this->clearCacheByPattern($store, $cacheKey);
        }
        
        Log::info("City cache cleared", ['city' => $city]);
    }

    /**
     * 快取預熱 - 載入熱門行政區資料
     */
    public function warmupHotDistricts(): void
    {
        foreach (self::HOT_DISTRICTS as $city => $districts) {
            foreach ($districts as $district) {
                // 預熱行政區統計資料
                $this->get('district_stats', $city, $district, function () use ($city, $district) {
                    return $this->loadDistrictStatistics($city, $district);
                });
                
                // 預熱城市統計資料
                $this->get('city_stats', $city, $district, function () use ($city) {
                    return $this->loadCityStatistics($city);
                });
            }
        }
        
        Log::info("Hot districts cache warmed up");
    }

    /**
     * 取得快取統計資訊
     */
    public function getCacheStats(): array
    {
        $stats = [];
        
        foreach (self::CACHE_LAYERS as $layer => $config) {
            $store = $config['store'];
            $stats[$layer] = [
                'store' => $store,
                'ttl' => $config['ttl'],
                'description' => $config['description'],
                'keys_count' => $this->getCacheKeysCount($store),
                'memory_usage' => $this->getCacheMemoryUsage($store),
            ];
        }
        
        return $stats;
    }

    /**
     * 建立快取鍵值
     */
    private function buildCacheKey(string $key, string $city, string $district, string $layer): string
    {
        return "{$layer}:{$key}:{$city}:{$district}";
    }

    /**
     * 檢查行政區是否有最近資料
     */
    private function hasRecentData(string $city, string $district): bool
    {
        // 這裡可以實作檢查邏輯，例如查詢最近是否有新資料
        // 暫時返回 true，實際實作時可以查詢資料庫
        return true;
    }

    /**
     * 提升到熱門快取
     */
    private function promoteToHotCache(string $key, $data, string $city, string $district): void
    {
        $hotKey = $this->buildCacheKey('promoted', $city, $district, 'hot');
        Cache::store('redis')->put($hotKey, $data, 3600);
    }

    /**
     * 載入行政區統計資料
     */
    private function loadDistrictStatistics(string $city, string $district): array
    {
        // 這裡實作載入行政區統計資料的邏輯
        return [
            'city' => $city,
            'district' => $district,
            'property_count' => 0,
            'avg_rent_per_ping' => 0,
        ];
    }

    /**
     * 載入城市統計資料
     */
    private function loadCityStatistics(string $city): array
    {
        // 這裡實作載入城市統計資料的邏輯
        return [
            'city' => $city,
            'total_properties' => 0,
            'avg_rent_per_ping' => 0,
        ];
    }

    /**
     * 清除符合模式的快取
     */
    private function clearCacheByPattern(string $store, string $pattern): void
    {
        if ($store === 'redis') {
            // Redis 支援模式匹配
            $keys = \Illuminate\Support\Facades\Redis::keys($pattern);
            if (!empty($keys)) {
                \Illuminate\Support\Facades\Redis::del($keys);
            }
        } else {
            // 其他快取驅動需要逐一清除
            // 這裡可以實作更精細的清除邏輯
        }
    }

    /**
     * 取得快取鍵值數量
     */
    private function getCacheKeysCount(string $store): int
    {
        if ($store === 'redis') {
            $keys = \Illuminate\Support\Facades\Redis::keys('*');
            return count($keys);
        }
        
        return 0;
    }

    /**
     * 取得快取記憶體使用量
     */
    private function getCacheMemoryUsage(string $store): int
    {
        if ($store === 'redis') {
            return \Illuminate\Support\Facades\Redis::memory('usage');
        }
        
        return 0;
    }
}
