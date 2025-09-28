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
        private GeoAggregationService $geoAggregationService,
        private MapDataService $mapDataService,
        private MapCacheService $mapCacheService,
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
                return $item['has_coordinates'];
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
        } elseif (($request->has('bounds') || $request->has(['north', 'south', 'east', 'west'])) && 
                  ($request->has('city') || $request->has('district'))) {
            $query = \App\Models\Property::query()->geocoded();
            $this->applyBounds($request, $query);

            if ($request->has('city')) {
                $query->byCity($request->city);
            }

            if ($request->has('district')) {
                $query->byDistrict($request->district);
            }

            $properties = $query->limit($request->get('limit', 1000))->get();

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
                return $item['has_coordinates'];
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
        $districts = $this->geoAggregationService->getDistrictsByCity($city);

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
        if (! $district) {
            return response()->json([
                'success' => false,
                'message' => '請指定行政區',
            ], 400);
        }

        $bounds = $this->geoAggregationService->getDistrictBounds($district);

        return response()->json([
            'success' => true,
            'bounds' => $bounds,
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

        $query = \App\Models\Property::query()->geocoded();
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
}
