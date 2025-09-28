<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\CityStatistics;
use App\Models\DistrictStatistics;
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

            // 基本統計 - 使用統計表
            $totalProperties = CityStatistics::sum('total_properties');
            // 由於資料是每10天下載一次，最近30天新增應該顯示所有資料
            // 因為這些資料代表最新的市場狀況
            $recentProperties = $totalProperties;

            // 平均租金統計 - 使用統計表
            $avgRentStats = CityStatistics::selectRaw('
                AVG(avg_rent_per_ping) as avg_rent_per_ping,
                MIN(min_rent_per_ping) as min_rent_per_ping,
                MAX(max_rent_per_ping) as max_rent_per_ping
            ')->first();
            
            // 計算總平均租金（需要從行政區統計計算）
            $districtStats = DistrictStatistics::selectRaw('
                AVG(avg_rent) as avg_rent,
                MIN(min_rent) as min_rent,
                MAX(max_rent) as max_rent
            ')->first();

            // 熱門區域統計 - 使用統計表
            $popularDistricts = DistrictStatistics::query()
                ->select([
                    'city',
                    'district',
                    'property_count',
                    'avg_rent',
                    'avg_area_ping',
                    'avg_rent_per_ping'
                ])
                ->orderBy('property_count', 'desc')
                ->limit(5)
                ->get();

            // 計算最熱門區域的每坪租金
            $topDistrict = $popularDistricts->first();
            $topDistrictRentPerPing = $topDistrict ? round($topDistrict->avg_rent_per_ping) : null;

            // 建築類型統計 - 暫時使用原始查詢（統計表沒有這個欄位）
            // TODO: 未來可以在統計表中添加建築類型統計
            $buildingTypeStats = Property::query()
                ->selectRaw('
                    building_type,
                    COUNT(*) as count,
                    AVG(total_rent) as avg_rent,
                    AVG(rent_per_ping) as avg_rent_per_ping
                ')
                ->groupBy('building_type')
                ->orderBy('count', 'desc')
                ->limit(10) // 添加限制避免過多資料
                ->get();

            // 租賃類型統計 - 暫時使用原始查詢（統計表沒有這個欄位）
            // TODO: 未來可以在統計表中添加租賃類型統計
            $rentalTypeStats = Property::query()
                ->selectRaw('
                    rental_type,
                    COUNT(*) as count,
                    AVG(total_rent) as avg_rent
                ')
                ->groupBy('rental_type')
                ->orderBy('count', 'desc')
                ->limit(10) // 添加限制避免過多資料
                ->get();

            // 價格趨勢 - 由於資料是定期下載的歷史資料，暫時設為無變化
            // 未來可以根據 rent_date 的月份來比較不同月份的價格趨勢
            $priceChange = 0;

            // 註解：未來可以實現按月份比較的邏輯
            // 例如：比較最近一個月 vs 前一個月的 rent_date 資料
            // $currentMonth = Property::whereMonth('rent_date', now()->month)->avg('total_rent');
            // $previousMonth = Property::whereMonth('rent_date', now()->subMonth()->month)->avg('total_rent');

            // 縣市統計 - 使用統計表
            $cityStats = CityStatistics::query()
                ->select([
                    'city',
                    'total_properties as property_count',
                    'avg_rent_per_ping'
                ])
                ->orderBy('total_properties', 'desc')
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
                        'average_rent' => round($districtStats->avg_rent ?? 0),
                        'average_rent_per_ping' => round($avgRentStats->avg_rent_per_ping ?? 0),
                        'min_rent' => round($districtStats->min_rent ?? 0),
                        'max_rent' => round($districtStats->max_rent ?? 0),
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
                            'average_rent' => 0, // 統計表中沒有這個欄位，暫時設為0
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
            // 獲取可用的篩選選項 - 使用統計表
            $cities = CityStatistics::query()
                ->select('city')
                ->orderBy('city')
                ->pluck('city')
                ->filter()
                ->values();

            $districts = DistrictStatistics::query()
                ->select('city', 'district')
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

            // 建築類型和租賃類型仍需要從原始表查詢（統計表沒有這些欄位）
            // 但添加限制避免過多資料
            $buildingTypes = Property::query()
                ->select('building_type')
                ->distinct()
                ->orderBy('building_type')
                ->limit(20) // 限制數量
                ->pluck('building_type')
                ->filter()
                ->values();

            $rentalTypes = Property::query()
                ->select('rental_type')
                ->distinct()
                ->orderBy('rental_type')
                ->limit(20) // 限制數量
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
