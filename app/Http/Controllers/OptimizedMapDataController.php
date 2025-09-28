<?php

namespace App\Http\Controllers;

use App\Services\IntelligentCacheService;
use App\Services\GeoAggregationService;
use App\Support\PerformanceMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OptimizedMapDataController extends Controller
{
    public function __construct(
        private IntelligentCacheService $intelligentCache,
        private GeoAggregationService $geoAggregation
    ) {}

    /**
     * 優化後的地圖資料 API
     */
    public function rentals(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('optimized.map.rentals');
        
        $filters = $this->buildFilters($request->all());
        $city = $filters['city'] ?? null;
        $district = $filters['district'] ?? null;

        // 使用智能快取取得資料
        $data = $this->intelligentCache->get(
            'map_rentals',
            $city ?? 'all',
            $district ?? 'all',
            function () use ($filters, $monitor) {
                $monitor->mark('cache_miss');
                
                // 快取未命中，從統計表查詢
                return $this->geoAggregation->getAggregatedProperties($filters);
            }
        );

        $monitor->mark('data_loaded');

        return response()->json([
            'success' => true,
            'data' => [
                'rentals' => $data,
                'statistics' => $this->calculateStatistics($data),
            ],
            'meta' => [
                'performance' => $monitor->summary(),
                'cache_layer' => $this->intelligentCache->getCacheLayer(
                    $city ?? 'all', 
                    $district ?? 'all'
                ),
            ],
        ]);
    }

    /**
     * 行政區統計資料
     */
    public function districtStats(Request $request): JsonResponse
    {
        $city = $request->get('city');
        $district = $request->get('district');

        if (!$city || !$district) {
            return response()->json([
                'success' => false,
                'message' => '請指定城市和行政區',
            ], 400);
        }

        // 使用智能快取取得行政區統計
        $stats = $this->intelligentCache->get(
            'district_stats',
            $city,
            $district,
            function () use ($city, $district) {
                return $this->loadDistrictStatistics($city, $district);
            }
        );

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * 城市統計資料
     */
    public function cityStats(Request $request): JsonResponse
    {
        $city = $request->get('city');

        if (!$city) {
            return response()->json([
                'success' => false,
                'message' => '請指定城市',
            ], 400);
        }

        // 使用智能快取取得城市統計
        $stats = $this->intelligentCache->get(
            'city_stats',
            $city,
            'all',
            function () use ($city) {
                return $this->loadCityStatistics($city);
            }
        );

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * 快取統計資訊
     */
    public function cacheStats(): JsonResponse
    {
        $stats = $this->intelligentCache->getCacheStats();

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * 清除特定行政區快取
     */
    public function clearDistrictCache(Request $request): JsonResponse
    {
        $city = $request->get('city');
        $district = $request->get('district');

        if (!$city || !$district) {
            return response()->json([
                'success' => false,
                'message' => '請指定城市和行政區',
            ], 400);
        }

        $this->intelligentCache->clearDistrictCache($city, $district);

        return response()->json([
            'success' => true,
            'message' => "已清除 {$city}{$district} 的快取",
        ]);
    }

    /**
     * 快取預熱
     */
    public function warmupCache(): JsonResponse
    {
        $this->intelligentCache->warmupHotDistricts();

        return response()->json([
            'success' => true,
            'message' => '快取預熱完成',
        ]);
    }

    /**
     * 建立篩選條件
     */
    private function buildFilters(array $requestData): array
    {
        $filters = [];

        $allowedFilters = [
            'city', 'district', 'building_type', 'rental_type',
            'min_rent', 'max_rent', 'min_rent_per_ping', 'max_rent_per_ping',
        ];

        foreach ($allowedFilters as $filter) {
            if (isset($requestData[$filter])) {
                $filters[$filter] = $requestData[$filter];
            }
        }

        return $filters;
    }

    /**
     * 計算統計資料
     */
    private function calculateStatistics($data): array
    {
        if (empty($data)) {
            return [
                'total_properties' => 0,
                'total_districts' => 0,
                'avg_rent_per_ping' => 0,
            ];
        }

        $collection = collect($data);

        return [
            'total_properties' => $collection->sum('property_count'),
            'total_districts' => $collection->count(),
            'avg_rent_per_ping' => $collection->avg('avg_rent_per_ping'),
        ];
    }

    /**
     * 載入行政區統計資料
     */
    private function loadDistrictStatistics(string $city, string $district): array
    {
        // 這裡實作從統計表載入資料的邏輯
        // 實際實作時會查詢 district_statistics 表
        return [
            'city' => $city,
            'district' => $district,
            'property_count' => 0,
            'avg_rent_per_ping' => 0,
            'last_updated' => now()->toISOString(),
        ];
    }

    /**
     * 載入城市統計資料
     */
    private function loadCityStatistics(string $city): array
    {
        // 這裡實作從統計表載入資料的邏輯
        // 實際實作時會查詢 city_statistics 表
        return [
            'city' => $city,
            'total_properties' => 0,
            'avg_rent_per_ping' => 0,
            'last_updated' => now()->toISOString(),
        ];
    }
}
