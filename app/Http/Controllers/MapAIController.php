<?php

namespace App\Http\Controllers;

use App\Events\MapDataUpdated;
use App\Models\Property;
use App\Services\AIMapOptimizationService;
use App\Services\MapDataService;
use App\Support\AdvancedPricePredictor;
use App\Support\PerformanceMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MapAIController extends Controller
{
    public function __construct(
        private AIMapOptimizationService $aiMapService,
        private MapDataService $mapDataService
    ) {}

    public function clusters(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('map.clusters');
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $query = Property::query()->geocoded();
        $this->applyBounds($request, $query);

        if ($request->has('city')) {
            $query->byCity($request->city);
        }

        if ($request->has('district')) {
            $query->byDistrict($request->district);
        }

        $properties = $query->select('latitude', 'longitude')->get();

        $monitor->mark('query_loaded');

        $data = $properties->map(function ($property) {
            return [
                'lat' => (float) $property->latitude,
                'lng' => (float) $property->longitude,
            ];
        })->toArray();

        $monitor->mark('payload_normalized');

        $algorithm = $request->get('algorithm', 'kmeans');
        $nClusters = (int) $request->get('clusters', 10);

        $serviceResult = $this->aiMapService->clusteringAlgorithm($data, $algorithm, $nClusters);
        $predictionResult = $monitor->trackModel('price_prediction', function () use ($data) {
            return $this->aiMapService->predictPrices($data);
        }, ['threshold_ms' => 300]);
        $priceSummary = $predictionResult['summary'] ?? [];
        $modelInfo = $predictionResult['model_info'] ?? [];

        $monitor->mark('algorithm_complete');
        $queryCount = count($connection->getQueryLog());
        $connection->disableQueryLog();

        $responseData = [
            'clusters' => $serviceResult['clusters'] ?? [],
            'algorithm_info' => $serviceResult['algorithm_info'] ?? [],
            'price_summary' => $priceSummary,
        ];

        broadcast(new MapDataUpdated($responseData, 'clusters'));

        return response()->json([
            'success' => $serviceResult['success'] ?? true,
            'data' => $responseData,
            'meta' => [
                'performance' => $monitor->summary([
                    'query_count' => $queryCount,
                ]),
                'models' => [
                    'price_prediction' => [
                        'version' => $modelInfo['version'] ?? AdvancedPricePredictor::MODEL_VERSION,
                        'average_confidence' => $priceSummary['average_confidence'] ?? null,
                    ],
                ],
            ],
        ]);
    }

    public function aiHeatmap(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('map.ai_heatmap');
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $query = Property::query()->geocoded();
        $this->applyBounds($request, $query);

        if ($request->has('city')) {
            $query->byCity($request->city);
        }

        if ($request->has('district')) {
            $query->byDistrict($request->district);
        }

        $properties = $query->select('latitude', 'longitude', 'rent_per_ping')->get();

        $monitor->mark('query_loaded');

        $data = $properties->map(function ($property) {
            return [
                'lat' => (float) $property->latitude,
                'lng' => (float) $property->longitude,
                'price' => (float) $property->rent_per_ping,
            ];
        })->toArray();

        $monitor->mark('payload_normalized');

        $heatmapResult = $this->aiMapService->generateHeatmap($data, 'medium');

        $monitor->mark('heatmap_generated');
        $queryCount = count($connection->getQueryLog());
        $connection->disableQueryLog();

        $responseData = [
            'heatmap_points' => $heatmapResult['points'] ?? [],
            'statistics' => $heatmapResult['statistics'] ?? [],
        ];

        broadcast(new MapDataUpdated($responseData, 'ai_heatmap'));

        return response()->json([
            'success' => true,
            'data' => $responseData,
            'meta' => [
                'performance' => $monitor->summary([
                    'query_count' => $queryCount,
                ]),
            ],
        ]);
    }

    public function predictPrices(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('map.predict_prices');

        $validator = Validator::make($request->all(), [
            'properties' => 'required|array|min:1',
            'properties.*.lat' => 'required|numeric|between:-90,90',
            'properties.*.lng' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '驗證失敗',
                'errors' => $validator->errors(),
            ], 422);
        }

        $properties = $request->input('properties');
        $predictionResult = $monitor->trackModel('price_prediction', function () use ($properties) {
            return $this->aiMapService->predictPrices($properties);
        }, ['threshold_ms' => 500]);

        return response()->json([
            'success' => $predictionResult['success'] ?? true,
            'data' => $predictionResult,
            'meta' => [
                'performance' => $monitor->summary(),
            ],
        ]);
    }

    public function optimizedData(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('map.optimized');
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $query = Property::query()->geocoded();
        $this->applyBounds($request, $query);

        if ($request->has('city')) {
            $query->byCity($request->city);
        }

        if ($request->has('district')) {
            $query->byDistrict($request->district);
        }

        $properties = $query->limit($request->get('limit', 1000))->get();

        $monitor->mark('query_loaded');

        // 檢查是否需要使用集群優化
        $shouldCluster = $properties->count() > 100; // 超過100個屬性時使用集群

        if ($shouldCluster) {
            // 使用集群優化
            $data = $properties->map(function ($property) {
                return [
                    'lat' => (float) $property->latitude,
                    'lng' => (float) $property->longitude,
                ];
            })->toArray();

            $algorithm = $request->get('algorithm', 'kmeans');
            $nClusters = min(20, max(5, intval($properties->count() / 10))); // 動態計算集群數

            $serviceResult = $this->aiMapService->clusteringAlgorithm($data, $algorithm, $nClusters);
            $predictionResult = $monitor->trackModel('price_prediction', function () use ($data) {
                return $this->aiMapService->predictPrices($data);
            }, ['threshold_ms' => 300]);
            $priceSummary = $predictionResult['summary'] ?? [];
            $modelInfo = $predictionResult['model_info'] ?? [];

            $queryCount = count($connection->getQueryLog());
            $connection->disableQueryLog();

            return response()->json([
                'success' => true,
                'data' => [
                    'type' => 'clusters',
                    'clusters' => $serviceResult['clusters'] ?? [],
                    'optimization_info' => [
                        'original_count' => $properties->count(),
                        'cluster_count' => count($serviceResult['clusters'] ?? []),
                        'reduction_ratio' => $properties->count() > 0 ? round(count($serviceResult['clusters'] ?? []) / $properties->count(), 2) : 0,
                    ],
                    'price_summary' => [
                        'total_predictions' => $priceSummary['total_predictions'] ?? 0,
                        'average_price' => $priceSummary['average_price'] ?? null,
                        'median_price' => $priceSummary['median_price'] ?? null,
                        'price_std_dev' => $priceSummary['price_std_dev'] ?? null,
                        'average_confidence' => $priceSummary['average_confidence'] ?? null,
                        'confidence_distribution' => $priceSummary['confidence_distribution'] ?? [],
                        'confidence_percentiles' => $priceSummary['confidence_percentiles'] ?? [],
                        'min_price' => $priceSummary['min_price'] ?? null,
                        'max_price' => $priceSummary['max_price'] ?? null,
                    ],
                ],
                'meta' => [
                    'performance' => $monitor->summary([
                        'query_count' => $queryCount,
                    ]),
                    'models' => [
                        'price_prediction' => [
                            'version' => $modelInfo['version'] ?? AdvancedPricePredictor::MODEL_VERSION,
                            'average_confidence' => $priceSummary['average_confidence'] ?? null,
                        ],
                    ],
                ],
            ]);
        } else {
            // 使用個別屬性
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

            return response()->json([
                'success' => true,
                'data' => [
                    'type' => 'properties',
                    'count' => $properties->count(),
                    'properties' => $properties->values()->map(function ($property, $index) use ($predictionLookup) {
                        $prediction = $this->mapDataService->matchPrediction($predictionLookup, $property->id, $index);

                        return [
                            'id' => $property->id,
                            'position' => [
                                'lat' => (float) $property->latitude,
                                'lng' => (float) $property->longitude,
                            ],
                            'info' => [
                                'city' => $property->city,
                                'district' => $property->district,
                                'building_type' => $property->building_type,
                                'area' => $property->area_ping,
                                'rent_per_ping' => $property->rent_per_ping,
                                'total_rent' => $property->total_rent,
                            ],
                            'price_prediction' => $this->mapDataService->formatPricePrediction($prediction),
                        ];
                    }),
                    'price_summary' => [
                        'total_predictions' => $properties->count(),
                        'average_price' => $predictionSummary['average_price'] ?? null,
                        'median_price' => $predictionSummary['median_price'] ?? null,
                        'price_std_dev' => $predictionSummary['price_std_dev'] ?? null,
                        'average_confidence' => $predictionSummary['average_confidence'] ?? null,
                        'confidence_distribution' => $predictionSummary['confidence_distribution'] ?? [],
                        'confidence_percentiles' => $predictionSummary['confidence_percentiles'] ?? [],
                        'min_price' => $predictionSummary['min_price'] ?? null,
                        'max_price' => $predictionSummary['max_price'] ?? null,
                    ],
                ],
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
        }
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
        return [
            'north' => (float) $request->input('north'),
            'south' => (float) $request->input('south'),
            'east' => (float) $request->input('east'),
            'west' => (float) $request->input('west'),
        ];
    }
}
