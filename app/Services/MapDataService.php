<?php

namespace App\Services;

use App\Support\AdvancedPricePredictor;
use Illuminate\Support\Collection;

class MapDataService
{
    /**
     * 建立預測資料載荷
     */
    public function buildPredictionPayload(Collection $properties): array
    {
        if ($properties->isEmpty()) {
            return [];
        }

        return $properties->values()->map(function ($property, $index) {
            return [
                'id' => $property->id ?? null,
                'index' => $index,
                'lat' => $property->latitude ?? null,
                'lng' => $property->longitude ?? null,
                'area' => $property->area_ping ?? null,
                'floor' => $property->total_floors ?? null,
                'age' => $property->building_age ?? null,
                'rent_per_ping' => $property->rent_per_ping ?? null,
                'building_type' => $property->building_type ?? null,
                'pattern' => $property->compartment_pattern ?? null,
                'rooms' => $property->bedrooms ?? null,
                'city' => $property->city ?? null,
                'district' => $property->district ?? null,
            ];
        })->all();
    }

    /**
     * 索引預測結果
     */
    public function indexPredictions(array $items): array
    {
        $indexed = [];
        foreach ($items as $item) {
            $key = $item['id'] ?? $item['index'] ?? null;
            if ($key === null) {
                continue;
            }
            $indexed[$key] = $item;
        }

        return $indexed;
    }

    /**
     * 匹配預測結果
     */
    public function matchPrediction(array $lookup, $id, $index): ?array
    {
        if ($id !== null && array_key_exists($id, $lookup)) {
            return $lookup[$id];
        }

        if ($index !== null && array_key_exists($index, $lookup)) {
            return $lookup[$index];
        }

        return null;
    }

    /**
     * 格式化價格預測
     */
    public function formatPricePrediction(?array $prediction): array
    {
        if ($prediction === null) {
            return [
                'value' => null,
                'range' => ['min' => null, 'max' => null],
                'confidence' => null,
                'model_version' => AdvancedPricePredictor::MODEL_VERSION,
            ];
        }

        return [
            'value' => $prediction['price'] ?? $prediction['predicted_price'] ?? null,
            'range' => $prediction['range'] ?? ['min' => null, 'max' => null],
            'confidence' => $prediction['confidence'] ?? null,
            'model_version' => $prediction['model_version'] ?? AdvancedPricePredictor::MODEL_VERSION,
        ];
    }

    /**
     * 轉換聚合資料為租屋資料格式
     */
    public function transformAggregatedToRentals(Collection $properties, array $predictionLookup = []): Collection
    {
        return $properties->map(function ($item, $index) use ($predictionLookup) {
            $baseData = [
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

            // 如果有預測資料，加入價格預測
            if (!empty($predictionLookup)) {
                $prediction = $this->matchPrediction($predictionLookup, $item['city'].'_'.$item['district'], $index);
                $baseData['price_prediction'] = $this->formatPricePrediction($prediction);
            }

            return $baseData;
        });
    }

    /**
     * 為聚合資料建構預測載荷
     */
    public function buildPredictionPayloadForAggregated(Collection $properties): array
    {
        if ($properties->isEmpty()) {
            return [];
        }

        return $properties->values()->map(function ($item, $index) {
            return [
                'id' => $item['city'].'_'.$item['district'],
                'index' => $index,
                'lat' => $item['latitude'] ?? null,
                'lng' => $item['longitude'] ?? null,
                'area' => $item['avg_area_ping'] ?? null,
                'floor' => 5, // 預設樓層
                'age' => 10, // 預設建築年齡
                'rent_per_ping' => $item['avg_rent_per_ping'] ?? null,
                'building_type' => '住宅大樓',
                'pattern' => '2房1廳1衛',
                'district' => $item['district'] ?? null,
            ];
        })->toArray();
    }

    /**
     * 轉換屬性為預測格式
     */
    public function transformPropertiesToPredictionFormat(Collection $properties, array $predictionLookup): Collection
    {
        return $properties->values()->map(function ($property, $index) use ($predictionLookup) {
            $prediction = $this->matchPrediction($predictionLookup, $property->id, $index);

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
                'price_prediction' => $this->formatPricePrediction($prediction),
            ];
        });
    }

    /**
     * 計算統計資料
     */
    public function calculateStatistics(Collection $aggregatedData): array
    {
        return [
            'count' => $aggregatedData->count(),
            'cities' => $aggregatedData->groupBy('city')->map->count(),
            'districts' => $aggregatedData->groupBy('district')->map->count(),
            'total_properties' => $aggregatedData->sum('property_count'),
            'avg_rent_per_ping' => $aggregatedData->avg('avg_rent_per_ping'),
        ];
    }

    /**
     * 建立篩選條件
     */
    public function buildFilters(array $requestData): array
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
}
