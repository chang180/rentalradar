<?php

namespace App\Http\Controllers;

use App\Events\MapDataUpdated;
use App\Services\AIMapOptimizationService;
use App\Services\GeoAggregationService;
use App\Services\MapCacheService;
use App\Services\MapDataService;
use App\Support\AdvancedPricePredictor;
use App\Support\PerformanceMonitor;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MapDataController extends Controller
{
    public function __construct(
        private MapCacheService $mapCacheService,
        private GeoAggregationService $geoAggregationService,
        private MapDataService $mapDataService,
        private AIMapOptimizationService $aiMapService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('map.index');
        $filters = $this->mapDataService->buildFilters($request->all());

        // 嘗試從快取取得資料
        $cachedData = $this->mapCacheService->getCachedMapRentals($filters);

        if ($cachedData !== null) {
            return response()->json([
                'success' => true,
                'data' => $cachedData,
                'meta' => [
                    'performance' => $monitor->summary(['cached' => true]),
                    'aggregation_type' => 'geo_center',
                ],
            ]);
        }

        // 快取未命中，從資料庫查詢
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        // 檢查是否有 bounds 參數，如果有則查詢個別屬性，否則使用聚合資料
        // 但如果沒有選擇縣市和行政區，即使有 bounds 也使用聚合資料
        if (($request->has('bounds') || $request->has(['north', 'south', 'east', 'west'])) && 
            !($request->has('city') || $request->has('district'))) {
            // 有 bounds 但沒有 city/district，使用聚合資料
            $aggregatedData = $this->geoAggregationService->getAggregatedProperties($filters);

            // 只回傳有座標的資料
            $properties = $aggregatedData->filter(function ($item) {
                return $item->has_coordinates ?? false;
            })->values();

            $monitor->mark('query_loaded');

            // 為聚合資料也生成價格預測
            $predictionInput = $this->mapDataService->buildPredictionPayloadForAggregated($properties);
            $predictionResult = $predictionInput === []
                ? ['predictions' => ['items' => [], 'summary' => []], 'model_info' => []]
                : $monitor->trackModel('price_prediction', function () use ($predictionInput) {
                    return $this->aiMapService->predictPrices($predictionInput);
                }, ['threshold_ms' => 300]);

            $predictionLookup = $this->mapDataService->indexPredictions($predictionResult['predictions'] ?? []);
            $predictionSummary = $predictionResult['summary'] ?? [];
            $modelInfo = $predictionResult['model_info'] ?? [];

            $queryCount = count($connection->getQueryLog());
            $connection->disableQueryLog();

            $responseData = [
                'rentals' => $this->mapDataService->transformAggregatedToRentals($properties, $predictionLookup),
                'statistics' => $this->calculateStatisticsForBounds($properties, $filters, $predictionSummary),
            ];

            // 快取結果
            $this->mapCacheService->cacheMapRentals($filters, $responseData);

            broadcast(new MapDataUpdated($responseData, 'properties'));

            return response()->json([
                'success' => true,
                'data' => $responseData,
                'meta' => [
                    'performance' => $monitor->summary([
                        'query_count' => $queryCount,
                    ]),
                    'aggregation_type' => 'geo_center',
                    'models' => [
                        'price_prediction' => [
                            'version' => $modelInfo['version'] ?? \App\Support\AdvancedPricePredictor::MODEL_VERSION,
                            'average_confidence' => $predictionSummary['average_confidence'] ?? null,
                        ],
                    ],
                ],
            ]);
        } elseif (($request->has('bounds') || $request->has(['north', 'south', 'east', 'west'])) && 
                  ($request->has('city') || $request->has('district'))) {
            
            // 特例處理：特殊城市需要查詢多個資料來源
            if ($request->has('city') && in_array($request->city, ['嘉義市', '新竹市'])) {
                $specialData = $this->handleSpecialCityCase($request->city, $request->district);
                $properties = collect($specialData);
            } else {
                $query = \App\Models\Property::query();
                $this->applyBounds($request, $query);

                if ($request->has('city')) {
                    $query->byCity($request->city);
                }

                if ($request->has('district')) {
                    $query->byDistrict($request->district);
                }

                $properties = $query->limit($request->get('limit', 1000))->get();
            }

            $monitor->mark('query_loaded');

            // 生成價格預測
            $predictionInput = $this->mapDataService->buildPredictionPayload($properties);
            $predictionResult = $predictionInput === []
                ? ['predictions' => ['items' => [], 'summary' => []], 'model_info' => []]
                : $monitor->trackModel('price_prediction', function () use ($predictionInput) {
                    return $this->aiMapService->predictPrices($predictionInput);
                }, ['threshold_ms' => 300]);

            $predictionLookup = $this->mapDataService->indexPredictions($predictionResult['predictions'] ?? []);
            $predictionSummary = $predictionResult['summary'] ?? [];
            $modelInfo = $predictionResult['model_info'] ?? [];

            $queryCount = count($connection->getQueryLog());
            $connection->disableQueryLog();

            $responseData = [
                'rentals' => $properties->values()->map(function ($property, $index) use ($predictionLookup) {
                    $prediction = $this->mapDataService->matchPrediction($predictionLookup, $property->id, $index);

                    return [
                        'id' => $property->id,
                        'title' => $property->city.$property->district,
                        'price' => $property->rent_per_ping,
                        'area' => $property->area_ping,
                        'location' => [
                            'lat' => (float) $property->latitude,
                            'lng' => (float) $property->longitude,
                            'address' => $property->city.$property->district,
                        ],
                        'price_prediction' => $this->mapDataService->formatPricePrediction($prediction),
                    ];
                }),
                'statistics' => $this->calculateStatisticsForBounds($properties, $filters, $predictionSummary),
            ];

            // 快取結果
            $this->mapCacheService->cacheMapRentals($filters, $responseData);

            return response()->json([
                'success' => true,
                'data' => $responseData,
                'meta' => [
                    'performance' => $monitor->summary([
                        'query_count' => $queryCount,
                    ]),
                    'models' => [
                        'price_prediction' => [
                            'version' => $modelInfo['version'] ?? AdvancedPricePredictor::MODEL_VERSION,
                            'average_confidence' => $predictionSummary['average_confidence'] ?? null,
                        ],
                    ],
                ],
            ]);
        } else {
            // 沒有 bounds 參數時使用聚合資料
            $aggregatedData = $this->geoAggregationService->getAggregatedProperties($filters);

            // 只回傳有座標的資料
            $properties = $aggregatedData->filter(function ($item) {
                return $item->has_coordinates ?? false;
            })->values();

            $monitor->mark('query_loaded');
            $queryCount = count($connection->getQueryLog());
            $connection->disableQueryLog();

            $responseData = [
                'rentals' => $this->mapDataService->transformAggregatedToRentals($properties),
                'statistics' => $this->mapDataService->calculateStatistics($properties),
            ];

            // 快取結果
            $this->mapCacheService->cacheMapRentals($filters, $responseData);

            broadcast(new MapDataUpdated($responseData, 'properties'));

            return response()->json([
                'success' => true,
                'data' => $responseData,
                'meta' => [
                    'performance' => $monitor->summary([
                        'query_count' => $queryCount,
                    ]),
                    'aggregation_type' => 'geo_center',
                ],
            ]);
        }
    }

    public function cities(): JsonResponse
    {
        // 嘗試從快取取得城市列表
        $cachedCities = $this->mapCacheService->getCachedCities();

        if ($cachedCities !== null) {
            return response()->json([
                'success' => true,
                'data' => $cachedCities,
            ]);
        }

        // 快取未命中，從資料庫查詢
        $cities = $this->geoAggregationService->getCities();

        // 快取結果
        $this->mapCacheService->cacheCities($cities->toArray());

        return response()->json([
            'success' => true,
            'data' => $cities,
        ]);
    }

    public function districts(Request $request): JsonResponse
    {
        $city = $request->get('city');
        if (! $city) {
            return response()->json([
                'success' => false,
                'message' => '請指定縣市',
            ], 400);
        }

        // 處理城市名稱不一致問題（台 vs 臺）
        $city = $this->normalizeCityName($city);

        // 嘗試從快取取得行政區列表
        $cachedDistricts = $this->mapCacheService->getCachedDistricts($city);

        if ($cachedDistricts !== null) {
            return response()->json([
                'success' => true,
                'data' => $cachedDistricts,
            ]);
        }

        // 快取未命中，從資料庫查詢
        if ($city === '嘉義市') {
            // 嘉義市特例：需要合併嘉義縣 嘉義市 和 嘉義市 的資料
            $chiayiCountyData = \App\Models\Property::where('city', '嘉義縣')
                ->where('district', '嘉義市')
                ->selectRaw('district, COUNT(*) as property_count, AVG(rent_per_ping) as avg_rent_per_ping')
                ->groupBy('district')
                ->get();
            
            $chiayiCityData = \App\Models\Property::where('city', '嘉義市')
                ->selectRaw('district, COUNT(*) as property_count, AVG(rent_per_ping) as avg_rent_per_ping')
                ->groupBy('district')
                ->get();
            
            $allDistricts = $chiayiCountyData->concat($chiayiCityData);
            
            $districts = $allDistricts->map(function ($item) {
                return [
                    'district' => $item->district,
                    'property_count' => (int) $item->property_count,
                    'avg_rent_per_ping' => round((float) $item->avg_rent_per_ping, 2),
                ];
            })->groupBy('district')->map(function ($items, $district) {
                return [
                    'district' => $district,
                    'property_count' => (int) $items->sum('property_count'),
                    'avg_rent_per_ping' => round((float) $items->avg('avg_rent_per_ping'), 2),
                ];
            })->values();
        } elseif ($city === '新竹市') {
            // 新竹市特例：需要合併新竹縣 新竹市 和 新竹市 的資料
            $hsinchuCountyData = \App\Models\Property::where('city', '新竹縣')
                ->where('district', '新竹市')
                ->selectRaw('district, COUNT(*) as property_count, AVG(rent_per_ping) as avg_rent_per_ping')
                ->groupBy('district')
                ->get();
            
            $hsinchuCityData = \App\Models\Property::where('city', '新竹市')
                ->selectRaw('district, COUNT(*) as property_count, AVG(rent_per_ping) as avg_rent_per_ping')
                ->groupBy('district')
                ->get();
            
            $allDistricts = $hsinchuCountyData->concat($hsinchuCityData);
            
            $districts = $allDistricts->map(function ($item) {
                return [
                    'district' => $item->district,
                    'property_count' => (int) $item->property_count,
                    'avg_rent_per_ping' => round((float) $item->avg_rent_per_ping, 2),
                ];
            })->groupBy('district')->map(function ($items, $district) {
                return [
                    'district' => $district,
                    'property_count' => (int) $items->sum('property_count'),
                    'avg_rent_per_ping' => round((float) $items->avg('avg_rent_per_ping'), 2),
                ];
            })->values();
        } else {
            $districts = $this->geoAggregationService->getDistrictsByCity($city);
        }

        // 快取結果
        $this->mapCacheService->cacheDistricts($city, $districts->toArray());

        return response()->json([
            'success' => true,
            'data' => $districts,
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('map.statistics');
        $filters = $this->mapDataService->buildFilters($request->all());

        // 嘗試從快取取得統計資料
        $cachedStatistics = $this->mapCacheService->getCachedStatistics($filters);

        if ($cachedStatistics !== null) {
            return response()->json([
                'success' => true,
                'data' => $cachedStatistics,
                'meta' => [
                    'performance' => $monitor->summary(['cached' => true]),
                ],
            ]);
        }

        // 快取未命中，從資料庫查詢
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $aggregatedData = $this->geoAggregationService->getAggregatedProperties($filters);

        $monitor->mark('query_loaded');
        $queryCount = count($connection->getQueryLog());
        $connection->disableQueryLog();

        $statistics = [
            'total_properties' => $aggregatedData->sum('property_count'),
            'total_districts' => $aggregatedData->count(),
            'total_cities' => $aggregatedData->groupBy('city')->count(),
            'avg_rent_per_ping' => $aggregatedData->avg('avg_rent_per_ping'),
            'min_rent_per_ping' => $aggregatedData->min('avg_rent_per_ping'),
            'max_rent_per_ping' => $aggregatedData->max('avg_rent_per_ping'),
            'cities' => $aggregatedData->groupBy('city')->map(function ($items) {
                return [
                    'property_count' => $items->sum('property_count'),
                    'avg_rent_per_ping' => $items->avg('avg_rent_per_ping'),
                ];
            }),
        ];

        // 快取結果
        $this->mapCacheService->cacheStatistics($filters, $statistics);

        return response()->json([
            'success' => true,
            'data' => $statistics,
            'meta' => [
                'performance' => $monitor->summary([
                    'query_count' => $queryCount,
                ]),
            ],
        ]);
    }

    public function districtBounds(Request $request): JsonResponse
    {
        $district = $request->get('district');
        $city = $request->get('city');
        
        if (! $district) {
            return response()->json([
                'success' => false,
                'message' => '請指定行政區',
            ], 400);
        }

        // 如果有城市參數，使用城市+行政區的組合查詢
        if ($city) {
            $bounds = $this->geoAggregationService->getDistrictBoundsByCity($city, $district);
        } else {
            $bounds = $this->geoAggregationService->getDistrictBounds($district);
        }

        return response()->json([
            'success' => true,
            'bounds' => $bounds,
        ]);
    }

    public function cityCenter(Request $request): JsonResponse
    {
        $city = $request->get('city');
        
        if (! $city) {
            return response()->json([
                'success' => false,
                'message' => '請指定縣市',
            ], 400);
        }

        $center = $this->geoAggregationService->getCityCenter($city);

        return response()->json([
            'success' => true,
            'center' => $center,
        ]);
    }

    /**
     * 應用地圖邊界篩選
     */
    private function applyBounds(Request $request, $query): void
    {
        if ($request->has('bounds')) {
            $bounds = $request->get('bounds');
            $query->whereBetween('latitude', [$bounds['south'], $bounds['north']])
                ->whereBetween('longitude', [$bounds['west'], $bounds['east']]);
        } elseif ($request->has(['north', 'south', 'east', 'west'])) {
            $bounds = $this->validateBounds($request);

            $query->whereBetween('latitude', [$bounds['south'], $bounds['north']])
                ->whereBetween('longitude', [$bounds['west'], $bounds['east']]);
        }
    }

    /**
     * 驗證地圖邊界參數
     */
    private function validateBounds(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'north' => ['required', 'numeric'],
            'south' => ['required', 'numeric'],
            'east' => ['required', 'numeric'],
            'west' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'success' => false,
                'error' => [
                    'code' => 'invalid_bounds',
                    'message' => 'Invalid map bounds provided.',
                    'details' => $validator->errors()->toArray(),
                ],
            ], 422));
        }

        return array_map('floatval', $validator->validated());
    }

    public function heatmapData(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('map.heatmap');
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $query = \App\Models\Property::query();
        $this->applyBounds($request, $query);

        if ($request->has('city')) {
            $query->byCity($request->city);
        }

        if ($request->has('district')) {
            $query->byDistrict($request->district);
        }

        $properties = $query->select('latitude', 'longitude', 'rent_per_ping')
            ->limit($request->get('limit', 2000))
            ->get();

        $heatmapData = $properties->map(function ($property) {
            return [
                (float) $property->latitude,
                (float) $property->longitude,
                (float) $property->rent_per_ping / 100, // 正規化強度值
            ];
        });

        $monitor->mark('transform_complete');
        $queryCount = count($connection->getQueryLog());
        $connection->disableQueryLog();

        return response()->json([
            'success' => true,
            'data' => [
                'heatmap_points' => $heatmapData,
                'count' => $properties->count(),
            ],
            'meta' => [
                'performance' => $monitor->summary([
                    'query_count' => $queryCount,
                ]),
            ],
        ]);
    }

    /**
     * 為有 bounds 的請求計算統計資料
     * 如果沒有選擇特定縣市，顯示全台統計；如果有選擇縣市，顯示該縣市統計
     */
    private function calculateStatisticsForBounds($properties, array $filters, array $predictionSummary): array
    {
        // 如果選擇了特定縣市，使用該縣市的統計資料
        if (isset($filters['city'])) {
            $normalizedCity = $this->normalizeCityName($filters['city']);
            $cityData = $this->geoAggregationService->getAggregatedProperties(['city' => $normalizedCity]);
            $baseStats = $this->mapDataService->calculateStatistics($cityData);
        } else {
            // 如果沒有選擇特定縣市，使用全台統計資料
            $allData = $this->geoAggregationService->getAggregatedProperties([]);
            $baseStats = $this->mapDataService->calculateStatistics($allData);
        }

        // 添加預測相關統計
        $baseStats['average_predicted_price'] = $predictionSummary['average_price'] ?? null;
        $baseStats['average_confidence'] = $predictionSummary['average_confidence'] ?? null;
        $baseStats['confidence_distribution'] = $predictionSummary['confidence_distribution'] ?? [];
        $baseStats['confidence_percentiles'] = $predictionSummary['confidence_percentiles'] ?? [];

        return $baseStats;
    }

    /**
     * 標準化城市名稱，處理台/臺等字符差異
     */
    private function normalizeCityName(string $city): string
    {
        // 處理常見的城市名稱差異
        $mapping = [
            '台中市' => '臺中市',
            '台北市' => '臺北市',
            '台南市' => '臺南市',
            '台東縣' => '臺東縣',
        ];

        return $mapping[$city] ?? $city;
    }

    /**
     * 處理特殊城市情況的資料查詢
     */
    private function handleSpecialCityCase(string $city, string $district = null): array
    {
        // 嘉義市特例：需要同時查詢嘉義縣 嘉義市 和 嘉義市 嘉義市 的資料
        if ($city === '嘉義市') {
            $query = \App\Models\Property::query();
            
            if ($district) {
                // 如果有指定行政區，查詢該行政區的資料
                $query->where(function ($q) use ($district) {
                    $q->where(function ($subQ) {
                        $subQ->where('city', '嘉義縣')
                             ->where('district', '嘉義市');
                    })->orWhere(function ($subQ) {
                        $subQ->where('city', '嘉義市')
                             ->where('district', '嘉義市');
                    });
                    
                    if ($district !== '嘉義市') {
                        $q->orWhere('city', '嘉義市')
                          ->where('district', $district);
                    }
                });
            } else {
                // 如果沒有指定行政區，查詢所有嘉義市相關資料
                $query->where(function ($q) {
                    $q->where(function ($subQ) {
                        $subQ->where('city', '嘉義縣')
                             ->where('district', '嘉義市');
                    })->orWhere('city', '嘉義市');
                });
            }
            
            return $query->get()->toArray();
        }
        
        // 新竹市特例：需要同時查詢新竹縣 新竹市 和 新竹市 新竹市 的資料
        if ($city === '新竹市') {
            $query = \App\Models\Property::query();
            
            if ($district) {
                // 如果有指定行政區，查詢該行政區的資料
                $query->where(function ($q) use ($district) {
                    $q->where(function ($subQ) {
                        $subQ->where('city', '新竹縣')
                             ->where('district', '新竹市');
                    })->orWhere(function ($subQ) {
                        $subQ->where('city', '新竹市')
                             ->where('district', '新竹市');
                    });
                    
                    if ($district !== '新竹市') {
                        $q->orWhere('city', '新竹市')
                          ->where('district', $district);
                    }
                });
            } else {
                // 如果沒有指定行政區，查詢所有新竹市相關資料
                $query->where(function ($q) {
                    $q->where(function ($subQ) {
                        $subQ->where('city', '新竹縣')
                             ->where('district', '新竹市');
                    })->orWhere('city', '新竹市');
                });
            }
            
            return $query->get()->toArray();
        }
        
        return [];
    }

    /**
     * 獲取行政區統計資料
     */
    public function districtStats(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('map.district_stats');
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        // 應用邊界篩選
        $query = \App\Models\Property::query();
        $this->applyBounds($request, $query);

        if ($request->has('city')) {
            $query->byCity($request->city);
        }

        if ($request->has('district')) {
            $query->byDistrict($request->district);
        }

        // 按行政區分組統計
        $districtStats = $query
            ->selectRaw('
                city,
                district,
                COUNT(*) as property_count,
                AVG(rent_per_ping) as avg_rent_per_ping,
                MIN(rent_per_ping) as min_rent_per_ping,
                MAX(rent_per_ping) as max_rent_per_ping,
                AVG(area_ping) as avg_area_ping,
                AVG(latitude) as center_lat,
                AVG(longitude) as center_lng
            ')
            ->groupBy('city', 'district')
            ->having('property_count', '>', 0)
            ->orderBy('property_count', 'desc')
            ->get();

        $monitor->mark('query_complete');
        $queryCount = count($connection->getQueryLog());
        $connection->disableQueryLog();

        $districts = $districtStats->map(function ($stat) {
            return [
                'id' => $stat->city . '_' . $stat->district,
                'city' => $stat->city,
                'district' => $stat->district,
                'center' => [
                    'lat' => (float) $stat->center_lat,
                    'lng' => (float) $stat->center_lng,
                ],
                'count' => (int) $stat->property_count,
                'avg_rent_per_ping' => round((float) $stat->avg_rent_per_ping, 2),
                'min_rent_per_ping' => round((float) $stat->min_rent_per_ping, 2),
                'max_rent_per_ping' => round((float) $stat->max_rent_per_ping, 2),
                'avg_area_ping' => round((float) $stat->avg_area_ping, 2),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'districts' => $districts,
                'total_districts' => $districts->count(),
                'total_properties' => $districtStats->sum('property_count'),
            ],
            'meta' => [
                'performance' => $monitor->summary([
                    'query_count' => $queryCount,
                ]),
            ],
        ]);
    }

    /**
     * 檢查地圖資料載入狀態
     */
    public function status(): JsonResponse
    {
        try {
            // 檢查 Redis 快取狀態
            $cacheStatus = $this->mapCacheService->getCacheStatus();
            
            // 檢查資料庫是否有資料
            $propertyCount = \App\Models\Property::count();
            $geocodedCount = \App\Models\Property::whereNotNull('latitude')->whereNotNull('longitude')->count();
            
            // 檢查聚合資料是否存在
            $aggregatedData = $this->geoAggregationService->getAggregatedProperties([]);
            $hasAggregatedData = $aggregatedData->count() > 0;
            
            // 只要有聚合資料就可以顯示，不強制要求原始資料
            $isReady = $hasAggregatedData;
            
            return response()->json([
                'success' => $isReady,
                'data' => [
                    'is_ready' => $isReady,
                    'property_count' => $propertyCount,
                    'geocoded_count' => $geocodedCount,
                    'aggregated_count' => $aggregatedData->count(),
                    'cache_status' => $cacheStatus,
                    'message' => $isReady 
                        ? '地圖資料已準備完成' 
                        : '地圖資料尚未準備完成，正在載入中...'
                ],
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'data' => [
                    'is_ready' => false,
                    'error' => $e->getMessage(),
                    'message' => '無法檢查地圖資料狀態'
                ],
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * 獲取價格分析資料
     */
    public function priceAnalysis(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('map.price_analysis');
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        // 應用邊界篩選
        $query = \App\Models\Property::query();
        $this->applyBounds($request, $query);

        if ($request->has('city')) {
            $query->byCity($request->city);
        }

        if ($request->has('district')) {
            $query->byDistrict($request->district);
        }

        $properties = $query
            ->select('latitude', 'longitude', 'rent_per_ping', 'total_rent')
            ->limit($request->get('limit', 2000))
            ->get();

        $monitor->mark('query_complete');

        // 計算價格區間
        $rents = $properties->pluck('rent_per_ping')->filter()->sort();
        $minRent = $rents->min() ?? 0;
        $maxRent = $rents->max() ?? 0;
        $avgRent = $rents->avg() ?? 0;

        // 定義價格等級
        $priceLevels = [
            'low' => ['min' => $minRent, 'max' => $avgRent * 0.7, 'color' => '#22c55e'], // 綠色
            'medium' => ['min' => $avgRent * 0.7, 'max' => $avgRent * 1.3, 'color' => '#f59e0b'], // 橙色
            'high' => ['min' => $avgRent * 1.3, 'max' => $avgRent * 1.8, 'color' => '#ef4444'], // 紅色
            'premium' => ['min' => $avgRent * 1.8, 'max' => $maxRent, 'color' => '#8b5cf6'], // 紫色
        ];

        $pricePoints = $properties->map(function ($property) use ($priceLevels, $avgRent, $minRent, $maxRent) {
            $rent = (float) $property->rent_per_ping;
            
            // 判斷價格等級
            $level = 'medium';
            $color = $priceLevels['medium']['color'];
            
            if ($rent <= $avgRent * 0.7) {
                $level = 'low';
                $color = $priceLevels['low']['color'];
            } elseif ($rent <= $avgRent * 1.3) {
                $level = 'medium';
                $color = $priceLevels['medium']['color'];
            } elseif ($rent <= $avgRent * 1.8) {
                $level = 'high';
                $color = $priceLevels['high']['color'];
            } else {
                $level = 'premium';
                $color = $priceLevels['premium']['color'];
            }

            return [
                'lat' => (float) $property->latitude,
                'lng' => (float) $property->longitude,
                'rent_per_ping' => $rent,
                'total_rent' => (float) $property->total_rent,
                'level' => $level,
                'color' => $color,
                'weight' => $maxRent > $minRent ? min(1, max(0.1, ($rent - $minRent) / ($maxRent - $minRent))) : 0.5,
            ];
        });

        $monitor->mark('transform_complete');
        $queryCount = count($connection->getQueryLog());
        $connection->disableQueryLog();

        return response()->json([
            'success' => true,
            'data' => [
                'price_points' => $pricePoints,
                'price_levels' => $priceLevels,
                'statistics' => [
                    'total_properties' => $properties->count(),
                    'min_rent' => round($minRent, 2),
                    'max_rent' => round($maxRent, 2),
                    'avg_rent' => round($avgRent, 2),
                    'median_rent' => round($rents->median() ?? 0, 2),
                ],
            ],
            'meta' => [
                'performance' => $monitor->summary([
                    'query_count' => $queryCount,
                ]),
            ],
        ]);
    }
}
