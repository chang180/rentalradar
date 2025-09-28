<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeoAggregationService
{
    /**
     * 取得聚合後的租賃資料（按縣市行政區分組）
     */
    public function getAggregatedProperties(array $filters = []): Collection
    {
        $query = Property::query()
            ->select([
                'city',
                'district',
                DB::raw('COUNT(*) as property_count'),
                DB::raw('AVG(total_rent) as avg_rent'),
                DB::raw('AVG(rent_per_ping) as avg_rent_per_ping'),
                DB::raw('MIN(total_rent) as min_rent'),
                DB::raw('MAX(total_rent) as max_rent'),
                DB::raw('AVG(area_ping) as avg_area_ping'),
                DB::raw('AVG(building_age) as avg_building_age'),
                DB::raw('SUM(CASE WHEN has_elevator = 1 THEN 1 ELSE 0 END) as elevator_count'),
                DB::raw('SUM(CASE WHEN has_management_organization = 1 THEN 1 ELSE 0 END) as management_count'),
                DB::raw('SUM(CASE WHEN has_furniture = 1 THEN 1 ELSE 0 END) as furniture_count'),
            ])
            ->groupBy('city', 'district');

        // 應用篩選條件
        if (isset($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        if (isset($filters['district'])) {
            $query->where('district', $filters['district']);
        }

        if (isset($filters['building_type'])) {
            $query->where('building_type', $filters['building_type']);
        }

        if (isset($filters['rental_type'])) {
            $query->where('rental_type', $filters['rental_type']);
        }

        if (isset($filters['min_rent'])) {
            $query->having('avg_rent', '>=', $filters['min_rent']);
        }

        if (isset($filters['max_rent'])) {
            $query->having('avg_rent', '<=', $filters['max_rent']);
        }

        if (isset($filters['min_rent_per_ping'])) {
            $query->having('avg_rent_per_ping', '>=', $filters['min_rent_per_ping']);
        }

        if (isset($filters['max_rent_per_ping'])) {
            $query->having('avg_rent_per_ping', '<=', $filters['max_rent_per_ping']);
        }

        $results = $query->get();

        // 為每個聚合結果添加地理中心點
        return $results->map(function ($item) {
            // 處理城市名稱不一致問題（台 vs 臺）
            $normalizedCity = $this->normalizeCityNameForGeoService($item->city);
            $center = $this->getCoordinatesDirect($normalizedCity, $item->district);

            return [
                'city' => $item->city,
                'district' => $item->district,
                'property_count' => $item->property_count,
                'avg_rent' => round($item->avg_rent, 0),
                'avg_rent_per_ping' => round($item->avg_rent_per_ping, 0),
                'min_rent' => round($item->min_rent, 0),
                'max_rent' => round($item->max_rent, 0),
                'avg_area_ping' => round($item->avg_area_ping ?? 0, 1),
                'avg_building_age' => round($item->avg_building_age ?? 0, 1),
                'elevator_ratio' => round(($item->elevator_count / $item->property_count) * 100, 1),
                'management_ratio' => round(($item->management_count / $item->property_count) * 100, 1),
                'furniture_ratio' => round(($item->furniture_count / $item->property_count) * 100, 1),
                'latitude' => $center['lat'] ?? null,
                'longitude' => $center['lng'] ?? null,
                'has_coordinates' => ! is_null($center) && isset($center['lat']) && isset($center['lng']),
            ];
        });
    }

    /**
     * 取得縣市層級的聚合資料
     */
    public function getCityAggregatedProperties(array $filters = []): Collection
    {
        $query = Property::query()
            ->select([
                'city',
                DB::raw('COUNT(*) as property_count'),
                DB::raw('AVG(total_rent) as avg_rent'),
                DB::raw('AVG(rent_per_ping) as avg_rent_per_ping'),
                DB::raw('MIN(total_rent) as min_rent'),
                DB::raw('MAX(total_rent) as max_rent'),
                DB::raw('AVG(area_ping) as avg_area_ping'),
                DB::raw('COUNT(DISTINCT district) as district_count'),
            ])
            ->groupBy('city');

        // 應用篩選條件
        if (isset($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        $results = $query->get();

        // 為每個縣市添加地理中心點（使用第一個行政區的中心點）
        return $results->map(function ($item) {
            $districts = TaiwanGeoCenterService::getDistrictsByCity($item->city);
            $firstDistrict = $districts[0] ?? null;
            $center = $firstDistrict ? TaiwanGeoCenterService::getGeoCenter($item->city, $firstDistrict) : null;

            return [
                'city' => $item->city,
                'property_count' => $item->property_count,
                'avg_rent' => round($item->avg_rent, 0),
                'avg_rent_per_ping' => round($item->avg_rent_per_ping, 0),
                'min_rent' => round($item->min_rent, 0),
                'max_rent' => round($item->max_rent, 0),
                'avg_area_ping' => round($item->avg_area_ping, 1),
                'district_count' => $item->district_count,
                'latitude' => $center['lat'] ?? null,
                'longitude' => $center['lng'] ?? null,
                'has_coordinates' => ! is_null($center),
            ];
        });
    }

    /**
     * 取得熱門區域統計（按物件數量排序）
     */
    public function getPopularDistricts(int $limit = 10): Collection
    {
        return $this->getAggregatedProperties()
            ->sortByDesc('property_count')
            ->take($limit);
    }

    /**
     * 取得租金最高的區域
     */
    public function getHighestRentDistricts(int $limit = 10): Collection
    {
        return $this->getAggregatedProperties()
            ->sortByDesc('avg_rent_per_ping')
            ->take($limit);
    }

    /**
     * 取得租金最低的區域
     */
    public function getLowestRentDistricts(int $limit = 10): Collection
    {
        return $this->getAggregatedProperties()
            ->sortBy('avg_rent_per_ping')
            ->take($limit);
    }

    /**
     * 取得建築類型統計
     */
    public function getBuildingTypeStats(): Collection
    {
        return Property::query()
            ->select([
                'building_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(total_rent) as avg_rent'),
                DB::raw('AVG(rent_per_ping) as avg_rent_per_ping'),
            ])
            ->groupBy('building_type')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->building_type,
                    'count' => $item->count,
                    'avg_rent' => round($item->avg_rent, 0),
                    'avg_rent_per_ping' => round($item->avg_rent_per_ping, 0),
                ];
            });
    }

    /**
     * 取得租賃類型統計
     */
    public function getRentalTypeStats(): Collection
    {
        return Property::query()
            ->select([
                'rental_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(total_rent) as avg_rent'),
            ])
            ->groupBy('rental_type')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->rental_type,
                    'count' => $item->count,
                    'avg_rent' => round($item->avg_rent, 0),
                ];
            });
    }

    /**
     * 取得縣市列表
     */
    public function getCities(): Collection
    {
        return $this->getAggregatedProperties()
            ->groupBy('city')
            ->map(function ($items, $city) {
                return [
                    'city' => $city,
                    'property_count' => $items->sum('property_count'),
                    'avg_rent_per_ping' => $items->avg('avg_rent_per_ping'),
                ];
            })
            ->sortByDesc('property_count')
            ->values();
    }

    /**
     * 取得指定縣市的行政區列表
     */
    public function getDistrictsByCity(string $city): Collection
    {
        return $this->getAggregatedProperties(['city' => $city])
            ->map(function ($item) {
                return [
                    'district' => $item['district'],
                    'property_count' => $item['property_count'],
                    'avg_rent_per_ping' => $item['avg_rent_per_ping'],
                ];
            })
            ->sortByDesc('property_count')
            ->values();
    }

    /**
     * 取得指定行政區的邊界資訊
     */
    public function getDistrictBounds(string $district): ?array
    {
        return TaiwanGeoCenterService::getDistrictBounds($district);
    }

    /**
     * 為地理服務標準化城市名稱，處理台/臺等字符差異
     */
    private function normalizeCityNameForGeoService(string $city): string
    {
        // 處理常見的城市名稱差異（資料庫使用「臺」，JSON 檔案使用「台」）
        $mapping = [
            '臺中市' => '台中市',
            '臺南市' => '台南市',
            '臺東縣' => '台東縣',
            // 臺北市在 JSON 中也是「臺北市」，不需要轉換
        ];

        return $mapping[$city] ?? $city;
    }

    /**
     * 直接從 JSON 檔案取得座標（使用 Redis 快取）
     */
    private function getCoordinatesDirect(string $city, string $district): ?array
    {
        $cacheKey = "geo_coordinates:{$city}:{$district}";
        
        // 嘗試從 Redis 快取取得
        $cached = \Illuminate\Support\Facades\Cache::store('redis')->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // 快取未命中，載入完整的地理資料
        $geoCenters = $this->loadGeoCentersWithCache();
        
        // 特例處理：嘉義縣 嘉義市 和 嘉義市 嘉義市 都對應到嘉義市
        $searchCity = $city;
        $searchDistrict = $district;
        
        if (($city === '嘉義縣' && $district === '嘉義市') || 
            ($city === '嘉義市' && $district === '嘉義市')) {
            $searchCity = '嘉義市';
            $searchDistrict = '嘉義市';
        }
        
        $center = $geoCenters[$searchCity][$searchDistrict] ?? null;
        $result = $center && isset($center['lat']) && isset($center['lng']) ? [
            'lat' => $center['lat'],
            'lng' => $center['lng']
        ] : null;
        
        // 快取結果到 Redis（快取 24 小時）
        \Illuminate\Support\Facades\Cache::store('redis')->put($cacheKey, $result, 86400);
        
        return $result;
    }

    /**
     * 載入地理中心點資料（使用 Redis 快取）
     */
    private function loadGeoCentersWithCache(): array
    {
        $cacheKey = 'taiwan_geo_centers_full';
        
        // 嘗試從 Redis 快取取得
        $cached = \Illuminate\Support\Facades\Cache::store('redis')->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // 快取未命中，從檔案載入
        $jsonPath = __DIR__ . '/../../storage/app/taiwan_geo_centers.json';
        $geoCenters = [];
        
        if (file_exists($jsonPath)) {
            $jsonContent = file_get_contents($jsonPath);
            $data = json_decode($jsonContent, true);
            $geoCenters = $data['geo_centers'] ?? [];
        }
        
        // 快取結果到 Redis（快取 24 小時）
        \Illuminate\Support\Facades\Cache::store('redis')->put($cacheKey, $geoCenters, 86400);
        
        return $geoCenters;
    }
}
