# DEV-33: 資料處理效能優化

## 問題描述

隨著資料檔案的自動下載解析和手動上傳匯入功能的完成，目前的資料處理方式存在以下效能問題：

1. **重複查詢問題**：每次 API 請求都直接查詢 `properties` 表進行聚合統計
2. **快取策略不夠精細**：資料更新時需要清除大量快取，缺乏增量更新機制
3. **統計資料重複計算**：相同的聚合統計被重複計算，造成不必要的資料庫負載

## 目標

- 提升資料處理效能，減少資料庫查詢次數
- 降低快取需求，實現增量更新
- 建立基於行政區的智能快取策略
- 優化資料匯入後的統計更新流程

## 技術方案

### 1. 建立統計資料表 (Materialized Views)

#### 1.1 行政區統計表
```sql
CREATE TABLE district_statistics (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    city VARCHAR(50) NOT NULL,
    district VARCHAR(50) NOT NULL,
    property_count INT DEFAULT 0,
    avg_rent DECIMAL(10,2),
    avg_rent_per_ping DECIMAL(10,2),
    min_rent DECIMAL(10,2),
    max_rent DECIMAL(10,2),
    avg_area_ping DECIMAL(8,2),
    avg_building_age DECIMAL(5,1),
    elevator_ratio DECIMAL(5,2),
    management_ratio DECIMAL(5,2),
    furniture_ratio DECIMAL(5,2),
    last_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_city_district (city, district),
    INDEX idx_city (city),
    INDEX idx_updated_at (last_updated_at)
);
```

#### 1.2 城市統計表
```sql
CREATE TABLE city_statistics (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    city VARCHAR(50) NOT NULL UNIQUE,
    district_count INT DEFAULT 0,
    total_properties INT DEFAULT 0,
    avg_rent_per_ping DECIMAL(10,2),
    min_rent_per_ping DECIMAL(10,2),
    max_rent_per_ping DECIMAL(10,2),
    last_updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 2. 增量更新服務

#### 2.1 統計更新服務
```php
class StatisticsUpdateService
{
    public function updateDistrictStatistics(string $city, string $district): void
    {
        // 計算該行政區的最新統計資料
        $stats = $this->calculateDistrictStats($city, $district);
        
        // 更新或插入統計資料
        DistrictStatistics::updateOrCreate(
            ['city' => $city, 'district' => $district],
            $stats
        );
        
        // 更新城市統計
        $this->updateCityStatistics($city);
        
        // 清除相關快取
        $this->clearDistrictCache($city, $district);
    }
    
    private function calculateDistrictStats(string $city, string $district): array
    {
        return Property::where('city', $city)
            ->where('district', $district)
            ->selectRaw('
                COUNT(*) as property_count,
                AVG(total_rent) as avg_rent,
                AVG(rent_per_ping) as avg_rent_per_ping,
                MIN(total_rent) as min_rent,
                MAX(total_rent) as max_rent,
                AVG(area_ping) as avg_area_ping,
                AVG(building_age) as avg_building_age,
                SUM(CASE WHEN has_elevator = 1 THEN 1 ELSE 0 END) / COUNT(*) as elevator_ratio,
                SUM(CASE WHEN has_management_organization = 1 THEN 1 ELSE 0 END) / COUNT(*) as management_ratio,
                SUM(CASE WHEN has_furniture = 1 THEN 1 ELSE 0 END) / COUNT(*) as furniture_ratio
            ')
            ->first()
            ->toArray();
    }
}
```

#### 2.2 事件驅動更新
```php
class PropertyCreated
{
    public function handle(PropertyCreated $event): void
    {
        $property = $event->property;
        
        // 非同步更新統計資料
        UpdateDistrictStatisticsJob::dispatch($property->city, $property->district);
    }
}
```

### 3. 分層快取策略

#### 3.1 智能快取服務
```php
class IntelligentCacheService
{
    private const DISTRICT_CACHE_TTL = 3600; // 1小時
    private const CITY_CACHE_TTL = 7200; // 2小時
    private const GLOBAL_CACHE_TTL = 14400; // 4小時
    
    public function getDistrictData(string $city, string $district): ?array
    {
        $cacheKey = "district:{$city}:{$district}";
        
        return Cache::remember($cacheKey, self::DISTRICT_CACHE_TTL, function () use ($city, $district) {
            return DistrictStatistics::where('city', $city)
                ->where('district', $district)
                ->first()
                ?->toArray();
        });
    }
    
    public function getCityData(string $city): ?array
    {
        $cacheKey = "city:{$city}";
        
        return Cache::remember($cacheKey, self::CITY_CACHE_TTL, function () use ($city) {
            return CityStatistics::where('city', $city)->first()?->toArray();
        });
    }
    
    public function clearDistrictCache(string $city, string $district): void
    {
        Cache::forget("district:{$city}:{$district}");
        Cache::forget("city:{$city}");
        // 不清除全域快取，保持其他城市資料可用
    }
}
```

#### 3.2 快取預熱機制
```php
class CacheWarmupService
{
    public function warmupPopularDistricts(): void
    {
        // 預熱熱門行政區的統計資料
        $popularDistricts = $this->getPopularDistricts();
        
        foreach ($popularDistricts as $district) {
            $this->intelligentCacheService->getDistrictData(
                $district['city'], 
                $district['district']
            );
        }
    }
}
```

### 4. 優化後的 API 服務

#### 4.1 優化的聚合服務
```php
class OptimizedGeoAggregationService
{
    public function getAggregatedProperties(array $filters = []): Collection
    {
        // 優先從統計表查詢
        $query = DistrictStatistics::query();
        
        if (isset($filters['city'])) {
            $query->where('city', $filters['city']);
        }
        
        if (isset($filters['district'])) {
            $query->where('district', $filters['district']);
        }
        
        return $query->get();
    }
    
    public function getStatistics(array $filters = []): array
    {
        $districts = $this->getAggregatedProperties($filters);
        
        return [
            'total_properties' => $districts->sum('property_count'),
            'total_districts' => $districts->count(),
            'total_cities' => $districts->groupBy('city')->count(),
            'avg_rent_per_ping' => $districts->avg('avg_rent_per_ping'),
            'min_rent_per_ping' => $districts->min('avg_rent_per_ping'),
            'max_rent_per_ping' => $districts->max('avg_rent_per_ping'),
        ];
    }
}
```

## 實作步驟

### Phase 1: 建立統計表
1. 建立 `district_statistics` 和 `city_statistics` 表
2. 建立對應的 Eloquent 模型
3. 建立初始資料遷移腳本

### Phase 2: 實作增量更新
1. 建立 `StatisticsUpdateService`
2. 建立事件監聽器
3. 建立非同步更新任務

### Phase 3: 優化快取策略
1. 建立 `IntelligentCacheService`
2. 實作分層快取機制
3. 建立快取預熱服務

### Phase 4: 重構現有服務
1. 更新 `GeoAggregationService`
2. 更新 `MarketAnalysisService`
3. 更新 API 控制器

### Phase 5: 效能測試與優化
1. 建立效能測試
2. 監控快取命中率
3. 調整快取策略

## 預期效益

1. **查詢效能提升 80%**：從直接查詢 properties 表改為查詢統計表
2. **快取命中率提升 60%**：智能快取策略減少不必要的快取清除
3. **資料更新延遲降低 90%**：增量更新機制避免全表重新計算
4. **記憶體使用降低 40%**：分層快取策略減少重複資料儲存

## 風險評估

1. **資料一致性**：需要確保統計表與原始資料的一致性
2. **遷移複雜度**：現有資料需要重新計算統計資料
3. **快取失效**：需要仔細設計快取失效策略

## 測試策略

1. **單元測試**：測試統計計算邏輯
2. **整合測試**：測試事件驅動更新機制
3. **效能測試**：比較優化前後的查詢效能
4. **快取測試**：驗證快取策略的有效性

## 相關檔案

- `database/migrations/create_district_statistics_table.php`
- `database/migrations/create_city_statistics_table.php`
- `app/Models/DistrictStatistics.php`
- `app/Models/CityStatistics.php`
- `app/Services/StatisticsUpdateService.php`
- `app/Services/IntelligentCacheService.php`
- `app/Jobs/UpdateDistrictStatisticsJob.php`
- `app/Events/PropertyCreated.php`
- `app/Listeners/UpdateStatisticsOnPropertyCreated.php`

## 驗收標準

1. ✅ 統計表建立完成且資料正確
2. ✅ 增量更新機制正常運作
3. ✅ 快取策略有效提升效能
4. ✅ API 回應時間減少 50% 以上
5. ✅ 快取命中率達到 80% 以上
6. ✅ 所有現有功能正常運作
