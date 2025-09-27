<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Services\MarketAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly MarketAnalysisService $marketAnalysisService) {}

    /**
     * 獲取 dashboard 統計數據
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $timeRange = $request->get('time_range', '30d');

            // 計算時間範圍
            $startDate = match ($timeRange) {
                '7d' => now()->subDays(7),
                '30d' => now()->subDays(30),
                '90d' => now()->subDays(90),
                '1y' => now()->subYear(),
                default => now()->subDays(30)
            };

            // 基本統計
            $totalProperties = Property::query()->geocoded()->count();
            $recentProperties = Property::query()
                ->geocoded()
                ->where('rent_date', '>=', $startDate)
                ->count();

            // 平均租金統計 - 使用 total_rent 欄位（實際租金）
            $avgRentStats = Property::query()
                ->geocoded()
                ->selectRaw('
                    AVG(total_rent) as avg_rent,
                    AVG(rent_per_ping) as avg_rent_per_ping,
                    MIN(total_rent) as min_rent,
                    MAX(total_rent) as max_rent
                ')
                ->first();

            // 熱門區域統計
            $popularDistricts = Property::query()
                ->geocoded()
                ->selectRaw('
                    city,
                    district,
                    COUNT(*) as property_count,
                    AVG(total_rent) as avg_rent,
                    AVG(area_ping) as avg_area_ping,
                    AVG(rent_per_ping) as avg_rent_per_ping
                ')
                ->groupBy('city', 'district')
                ->orderBy('property_count', 'desc')
                ->limit(5)
                ->get();

            // 計算最熱門區域的每坪租金
            $topDistrict = $popularDistricts->first();
            $topDistrictRentPerPing = $topDistrict ? round($topDistrict->avg_rent_per_ping) : null;

            // 建築類型統計
            $buildingTypeStats = Property::query()
                ->geocoded()
                ->selectRaw('
                    building_type,
                    COUNT(*) as count,
                    AVG(total_rent) as avg_rent,
                    AVG(rent_per_ping) as avg_rent_per_ping
                ')
                ->groupBy('building_type')
                ->orderBy('count', 'desc')
                ->get();

            // 租賃類型統計
            $rentalTypeStats = Property::query()
                ->geocoded()
                ->selectRaw('
                    rental_type,
                    COUNT(*) as count,
                    AVG(total_rent) as avg_rent
                ')
                ->groupBy('rental_type')
                ->orderBy('count', 'desc')
                ->get();

            // 價格趨勢（最近30天 vs 前30天）
            $currentPeriod = Property::query()
                ->geocoded()
                ->where('rent_date', '>=', now()->subDays(30))
                ->avg('total_rent');

            $previousPeriod = Property::query()
                ->geocoded()
                ->whereBetween('rent_date', [now()->subDays(60), now()->subDays(30)])
                ->avg('total_rent');

            $priceChange = $previousPeriod > 0
                ? (($currentPeriod - $previousPeriod) / $previousPeriod) * 100
                : 0;

            // 縣市統計
            $cityStats = Property::query()
                ->geocoded()
                ->selectRaw('
                    city,
                    COUNT(*) as property_count,
                    AVG(total_rent) as avg_rent,
                    AVG(rent_per_ping) as avg_rent_per_ping
                ')
                ->groupBy('city')
                ->orderBy('property_count', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => [
                        'total_properties' => $totalProperties,
                        'recent_properties' => $recentProperties,
                        'time_range' => $timeRange,
                    ],
                    'rent_statistics' => [
                        'average_rent' => round($avgRentStats->avg_rent ?? 0),
                        'average_rent_per_ping' => round($avgRentStats->avg_rent_per_ping ?? 0),
                        'min_rent' => round($avgRentStats->min_rent ?? 0),
                        'max_rent' => round($avgRentStats->max_rent ?? 0),
                        'price_change_percent' => round($priceChange, 1),
                        'price_change_direction' => $priceChange >= 0 ? 'up' : 'down',
                    ],
                    'popular_districts' => $popularDistricts->map(function ($district) {
                        return [
                            'city' => $district->city,
                            'district' => $district->district,
                            'property_count' => $district->property_count,
                            'average_rent' => round($district->avg_rent),
                            'average_area_ping' => round($district->avg_area_ping, 1),
                            'average_rent_per_ping' => round($district->avg_rent_per_ping),
                        ];
                    }),
                    'top_district_rent_per_ping' => $topDistrictRentPerPing,
                    'building_types' => $buildingTypeStats->map(function ($type) {
                        return [
                            'type' => $type->building_type,
                            'count' => $type->count,
                            'average_rent' => round($type->avg_rent),
                            'average_rent_per_ping' => round($type->avg_rent_per_ping),
                        ];
                    }),
                    'rental_types' => $rentalTypeStats->map(function ($type) {
                        return [
                            'type' => $type->rental_type,
                            'count' => $type->count,
                            'average_rent' => round($type->avg_rent),
                        ];
                    }),
                    'city_statistics' => $cityStats->map(function ($city) {
                        return [
                            'city' => $city->city,
                            'property_count' => $city->property_count,
                            'average_rent' => round($city->avg_rent),
                            'average_rent_per_ping' => round($city->avg_rent_per_ping),
                        ];
                    }),
                    'last_updated' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取統計數據失敗: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * 獲取快速操作數據
     */
    public function getQuickActions(): JsonResponse
    {
        try {
            // 獲取可用的篩選選項
            $cities = Property::query()
                ->geocoded()
                ->select('city')
                ->distinct()
                ->orderBy('city')
                ->pluck('city')
                ->filter()
                ->values();

            $districts = Property::query()
                ->geocoded()
                ->select('city', 'district')
                ->distinct()
                ->orderBy('city')
                ->orderBy('district')
                ->get()
                ->map(function ($item) {
                    return [
                        'city' => $item->city,
                        'district' => $item->district,
                        'label' => $item->city.$item->district,
                    ];
                });

            $buildingTypes = Property::query()
                ->geocoded()
                ->select('building_type')
                ->distinct()
                ->orderBy('building_type')
                ->pluck('building_type')
                ->filter()
                ->values();

            $rentalTypes = Property::query()
                ->geocoded()
                ->select('rental_type')
                ->distinct()
                ->orderBy('rental_type')
                ->pluck('rental_type')
                ->filter()
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'available_filters' => [
                        'cities' => $cities,
                        'districts' => $districts,
                        'building_types' => $buildingTypes,
                        'rental_types' => $rentalTypes,
                    ],
                    'ai_features' => [
                        'price_prediction' => true,
                        'market_analysis' => true,
                        'anomaly_detection' => true,
                        'recommendation_engine' => true,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '獲取快速操作數據失敗: '.$e->getMessage(),
            ], 500);
        }
    }
}
