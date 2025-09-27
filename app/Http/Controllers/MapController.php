<?php

namespace App\Http\Controllers;

use App\Events\MapDataUpdated;
use App\Events\RealTimeNotification;
use App\Models\Property;
use App\Services\AIMapOptimizationService;
use App\Services\GeoAggregationService;
use App\Support\AdvancedPricePredictor;
use App\Support\PerformanceMonitor;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MapController extends Controller
{
    public function __construct(
        private AIMapOptimizationService $aiMapService,
        private GeoAggregationService $geoAggregationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $monitor = PerformanceMonitor::start('map.index');
        $connection = DB::connection();
        $connection->flushQueryLog();
        $connection->enableQueryLog();

        // 使用聚合服務取得地理中心點資料
        $filters = $this->buildFilters($request);
        $aggregatedData = $this->geoAggregationService->getAggregatedProperties($filters);

        // 只回傳有座標的資料
        $properties = $aggregatedData->filter(function ($item) {
            return $item['has_coordinates'];
        })->values();

        $monitor->mark('query_loaded');
        $queryCount = count($connection->getQueryLog());
        $connection->disableQueryLog();

        $responseData = [
            'rentals' => $properties->map(function ($item) {
                return [
                    'id' => $item['city'].'_'.$item['district'],
                    'title' => $item['city'].$item['district'],
                    'price' => $item['avg_rent_per_ping'],
                    'area' => $item['avg_area_ping'],
                    'location' => [
                        'lat' => (float) $item['latitude'],
                        'lng' => (float) $item['longitude'],
                        'address' => $item['city'].$item['district'],
                    ],
                    'property_count' => $item['property_count'],
                    'avg_rent' => $item['avg_rent'],
                    'min_rent' => $item['min_rent'],
                    'max_rent' => $item['max_rent'],
                    'elevator_ratio' => $item['elevator_ratio'],
                    'management_ratio' => $item['management_ratio'],
                    'furniture_ratio' => $item['furniture_ratio'],
                ];
            }),
            'statistics' => [
                'count' => $properties->count(),
                'cities' => $properties->groupBy('city')->map->count(),
                'districts' => $properties->groupBy('district')->map->count(),
                'total_properties' => $properties->sum('property_count'),
                'avg_rent_per_ping' => $properties->avg('avg_rent_per_ping'),
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
                'aggregation_type' => 'geo_center',
            ],
        ]);
    }

    public function optimizedData(Request $request): JsonResponse
    {
        return $this->index($request);
    }

    public function cities(): JsonResponse
    {
        $cities = $this->geoAggregationService->getCities();

        return response()->json([
            'success' => true,
            'data' => $cities,
        ]);
    }

    public function districts(Request $request): JsonResponse
    {
        $city = $request->get('city');
        if (!$city) {
            return response()->json([
                'success' => false,
                'message' => '請指定縣市',
            ], 400);
        }

        $districts = $this->geoAggregationService->getDistrictsByCity($city);

        return response()->json([
            'success' => true,
            'data' => $districts,
        ]);
    }

    /**
     * 建立篩選條件
     */
    private function buildFilters(Request $request): array
    {
        $filters = [];

        if ($request->has('city')) {
            $filters['city'] = $request->city;
        }

        if ($request->has('district')) {
            $filters['district'] = $request->district;
        }

        return $filters;
    }
}