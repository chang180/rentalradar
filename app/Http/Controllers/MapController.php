<?php

namespace App\Http\Controllers;

use App\Events\MapDataUpdated;
use App\Events\RealTimeNotification;
use App\Models\Property;
use App\Services\AIMapOptimizationService;
use App\Support\PerformanceMonitor;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MapController extends Controller
{
    public function __construct(private AIMapOptimizationService $aiMapService)
    {
    }
    public function index(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('map.index');
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $query = Property::query()->geocoded();
        $this->applyBounds($request, $query);

        if ($request->has('district')) {
            $query->byDistrict($request->district);
        }

        if ($request->has(['start_date', 'end_date'])) {
            $query->rentDateBetween($request->start_date, $request->end_date);
        }

        $properties = $query->select([
            'id',
            'district',
            'village',
            'road',
            'building_type',
            'total_floor_area',
            'rent_per_month',
            'total_rent',
            'rent_date',
            'latitude',
            'longitude',
            'full_address',
            'compartment_pattern'
        ])
        ->limit($request->get('limit', 1000))
        ->get();

        $monitor->mark('query_loaded');
        $queryCount = count($connection->getQueryLog());
        $connection->disableQueryLog();

        $responseData = [
            'rentals' => $properties->map(function ($property) {
                return [
                    'id' => $property->id,
                    'title' => $property->full_address,
                    'price' => $property->rent_per_month,
                    'area' => $property->total_floor_area,
                    'location' => [
                        'lat' => (float) $property->latitude,
                        'lng' => (float) $property->longitude,
                        'address' => $property->full_address,
                    ],
                ];
            }),
            'statistics' => [
                'count' => $properties->count(),
                'districts' => $properties->groupBy('district')->map->count(),
            ],
        ];

        broadcast(new MapDataUpdated($responseData, 'properties'));

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

    public function statistics(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('map.statistics');
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $query = Property::query()->geocoded();
        $this->applyBounds($request, $query);

        if ($request->has('district')) {
            $query->byDistrict($request->district);
        }

        $stats = [
            'total_properties' => $query->count(),
            'avg_rent_per_month' => $query->avg('rent_per_month'),
            'avg_total_rent' => $query->avg('total_rent'),
            'avg_floor_area' => $query->avg('total_floor_area'),
            'district_stats' => $query->selectRaw('
                district,
                COUNT(*) as count,
                AVG(rent_per_month) as avg_rent_per_month,
                AVG(total_rent) as avg_total_rent,
                MIN(rent_per_month) as min_rent,
                MAX(rent_per_month) as max_rent
            ')
            ->groupBy('district')
            ->get(),
            'building_type_stats' => $query->selectRaw('
                building_type,
                COUNT(*) as count,
                AVG(rent_per_month) as avg_rent_per_month
            ')
            ->groupBy('building_type')
            ->get(),
        ];

        $monitor->mark('stats_ready');
        $queryCount = count($connection->getQueryLog());
        $connection->disableQueryLog();

        return response()->json([
            'success' => true,
            'data' => $stats,
            'meta' => [
                'performance' => $monitor->summary([
                    'query_count' => $queryCount,
                ]),
            ],
        ]);
    }

    public function heatmapData(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('map.heatmap');
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $query = Property::query()->geocoded();
        $this->applyBounds($request, $query);

        $properties = $query->select('latitude', 'longitude', 'rent_per_month')
            ->limit($request->get('limit', 2000))
            ->get();

        $heatmapData = $properties->map(function ($property) {
            return [
                (float) $property->latitude,
                (float) $property->longitude,
                (float) $property->rent_per_month / 1000 // 正規化強度值
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

    public function districts(): JsonResponse
    {
        $districts = Property::query()
            ->select('district')
            ->selectRaw('COUNT(*) as property_count')
            ->selectRaw('AVG(rent_per_month) as avg_rent')
            ->groupBy('district')
            ->orderBy('property_count', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $districts,
        ]);
    }

    public function clusters(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('map.clusters');
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $query = Property::query()->geocoded();
        $this->applyBounds($request, $query);

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

        $monitor->mark('algorithm_complete');
        $queryCount = count($connection->getQueryLog());
        $connection->disableQueryLog();

        $responseData = [
            'clusters' => $serviceResult['clusters'] ?? [],
            'algorithm_info' => $serviceResult['algorithm_info'] ?? [],
        ];

        broadcast(new MapDataUpdated($responseData, 'clusters'));

        return response()->json([
            'success' => $serviceResult['success'] ?? true,
            'data' => $responseData,
            'meta' => [
                'performance' => $monitor->summary([
                    'query_count' => $queryCount,
                ]),
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

        $properties = $query->select('latitude', 'longitude', 'total_rent')->get();

        $monitor->mark('query_loaded');

        $data = $properties->map(function ($property) {
            return [
                'lat' => (float) $property->latitude,
                'lng' => (float) $property->longitude,
                'price' => (float) $property->total_rent,
            ];
        })->toArray();

        $monitor->mark('payload_normalized');

        $resolution = $request->get('resolution', 'medium');
        $serviceResult = $this->aiMapService->generateHeatmap($data, $resolution);

        $monitor->mark('heatmap_ready');
        $queryCount = count($connection->getQueryLog());
        $connection->disableQueryLog();

        return response()->json([
            'success' => $serviceResult['success'] ?? true,
            'data' => [
                'heatmap_points' => $serviceResult['heatmap_points'] ?? [],
                'color_scale' => $serviceResult['color_scale'] ?? [],
                'statistics' => $serviceResult['statistics'] ?? [],
            ],
            'meta' => [
                'performance' => $monitor->summary([
                    'query_count' => $queryCount,
                ]),
            ],
        ]);
    }

    public function predictPrices(Request $request): JsonResponse
    {
        $inputData = $request->validate([
            'properties' => 'required|array',
            'properties.*.lat' => 'required|numeric',
            'properties.*.lng' => 'required|numeric',
            'properties.*.area' => 'numeric',
            'properties.*.floor' => 'numeric',
            'properties.*.age' => 'numeric',
        ]);

        $result = $this->aiMapService->predictPrices($inputData['properties']);

        return response()->json($result);
    }

    public function optimizedData(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('map.optimized');
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $query = Property::query()->geocoded();
        $this->applyBounds($request, $query);

        if ($request->has('district')) {
            $query->byDistrict($request->district);
        }

        $zoom = (int) $request->get('zoom', 12);
        $limit = $this->calculateOptimalLimit($zoom);

        $properties = $query->select([
            'id',
            'district',
            'building_type',
            'total_floor_area',
            'rent_per_month',
            'total_rent',
            'latitude',
            'longitude'
        ])
        ->limit($limit)
        ->get();

        $monitor->mark('query_loaded');

        // 如果資料點太多，使用 AI 聚合演算法
        if ($properties->count() > 100) {
            $data = $properties->map(function ($property) {
                return [
                    'lat' => (float) $property->latitude,
                    'lng' => (float) $property->longitude,
                    'price' => (float) $property->total_rent,
                ];
            })->toArray();

            $clusters = $this->aiMapService->clusteringAlgorithm($data, 'grid', 20);

            $monitor->mark('clusters_calculated');
            $queryCount = count($connection->getQueryLog());
            $connection->disableQueryLog();

            return response()->json([
                'success' => true,
                'data' => [
                    'type' => 'clusters',
                    'clusters' => $clusters['clusters'] ?? [],
                    'optimization_info' => [
                        'original_count' => $properties->count(),
                        'cluster_count' => count($clusters['clusters'] ?? []),
                        'reduction_ratio' => round((1 - count($clusters['clusters'] ?? []) / $properties->count()) * 100, 2)
                    ],
                ],
                'meta' => [
                    'performance' => $monitor->summary([
                        'query_count' => $queryCount,
                    ]),
                ],
            ]);
        }

        // 資料點較少時直接返回
        $monitor->mark('payload_transformed');
        $queryCount = count($connection->getQueryLog());
        $connection->disableQueryLog();

        return response()->json([
            'success' => true,
            'data' => [
                'type' => 'properties',
                'properties' => $properties->map(function ($property) {
                    return [
                        'id' => $property->id,
                        'position' => [
                            'lat' => (float) $property->latitude,
                            'lng' => (float) $property->longitude,
                        ],
                        'info' => [
                            'district' => $property->district,
                            'building_type' => $property->building_type,
                            'area' => $property->total_floor_area,
                            'rent_per_month' => $property->rent_per_month,
                            'total_rent' => $property->total_rent,
                        ]
                    ];
                }),
                'count' => $properties->count(),
            ],
            'meta' => [
                'performance' => $monitor->summary([
                    'query_count' => $queryCount,
                ]),
            ],
        ]);
    }

    private function calculateOptimalLimit(int $zoom): int
    {
        // 根據縮放級別優化資料載入量
        return match(true) {
            $zoom <= 10 => 50,
            $zoom <= 12 => 200,
            $zoom <= 14 => 500,
            default => 1000
        };
    }

    private function applyBounds(Request $request, $query): void
    {
        $bounds = $this->extractBounds($request);
        if ($bounds === null) {
            return;
        }

        $query->withinBounds($bounds['north'], $bounds['south'], $bounds['east'], $bounds['west']);
    }

    private function extractBounds(Request $request): ?array
    {
        $incoming = $request->get('bounds');
        $hasDirect = $request->has(['north', 'south', 'east', 'west']);

        if ($incoming === null && !$hasDirect) {
            return null;
        }

        $candidate = [
            'north' => is_array($incoming) ? ($incoming['north'] ?? $incoming['top'] ?? null) : null,
            'south' => is_array($incoming) ? ($incoming['south'] ?? $incoming['bottom'] ?? null) : null,
            'east' => is_array($incoming) ? ($incoming['east'] ?? $incoming['right'] ?? null) : null,
            'west' => is_array($incoming) ? ($incoming['west'] ?? $incoming['left'] ?? null) : null,
        ];

        if ($hasDirect) {
            $candidate['north'] = $candidate['north'] ?? $request->get('north');
            $candidate['south'] = $candidate['south'] ?? $request->get('south');
            $candidate['east'] = $candidate['east'] ?? $request->get('east');
            $candidate['west'] = $candidate['west'] ?? $request->get('west');
        }

        if (!array_filter($candidate, static fn ($value) => $value !== null)) {
            return null;
        }

        $validator = Validator::make($candidate, [
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

    public function sendNotification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:255',
            'type' => 'string|in:info,success,warning,error',
            'user_id' => 'nullable|exists:users,id',
            'data' => 'nullable|array',
        ]);

        $notification = new RealTimeNotification(
            message: $validated['message'],
            type: $validated['type'] ?? 'info',
            data: $validated['data'] ?? null,
            userId: $validated['user_id'] ?? null
        );

        broadcast($notification);

        return response()->json([
            'success' => true,
            'message' => 'Notification sent successfully',
        ]);
    }
}
