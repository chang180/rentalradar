<?php

namespace App\Services;

use App\Models\Prediction;
use App\Models\Property;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PredictionService
{
    private AIModelTrainingService $trainingService;

    private const PREDICTION_CACHE_TTL = 3600; // 1小時

    public function __construct(AIModelTrainingService $trainingService)
    {
        $this->trainingService = $trainingService;
    }

    public function predictRentPrice(array $propertyData): array
    {
        try {
            // 1. 載入訓練好的模型
            $model = $this->trainingService->loadModel();

            if (! $model) {
                return [
                    'status' => 'error',
                    'message' => 'No trained model available. Please train the model first.',
                ];
            }

            // 2. 特徵預處理
            $features = $this->preprocessFeatures($propertyData, $model->normalization_params);

            // 3. 執行預測
            $prediction = $this->executePrediction($model, $features);

            // 4. 計算信心度和範圍
            $confidence = $this->calculateConfidence($model, $features);
            $range = $this->calculatePredictionRange($prediction, $confidence);

            // 5. 生成解釋
            $explanations = $this->generateExplanations($model, $features, $prediction);

            // 6. 儲存預測結果（如果有 property_id）
            if (isset($propertyData['property_id'])) {
                $this->savePrediction($propertyData['property_id'], $prediction, $confidence, $range, $explanations, $model->version);
            }

            return [
                'status' => 'success',
                'predicted_price' => round($prediction, 2),
                'confidence' => round($confidence, 4),
                'range_min' => round($range['min'], 2),
                'range_max' => round($range['max'], 2),
                'factors' => $this->getImportantFactors($model, $features),
                'explanations' => $explanations,
                'model_version' => $model->version,
                'prediction_date' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('Prediction failed', [
                'error' => $e->getMessage(),
                'property_data' => $propertyData,
            ]);

            return [
                'status' => 'error',
                'message' => 'Prediction failed: '.$e->getMessage(),
            ];
        }
    }

    public function getMarketTrends(?string $district = null): array
    {
        $cacheKey = 'market_trends_'.($district ?? 'all');

        return Cache::remember($cacheKey, self::PREDICTION_CACHE_TTL, function () use ($district) {
            return $this->analyzeMarketTrends($district);
        });
    }

    public function predictPropertyValue(int $propertyId): array
    {
        $property = Property::find($propertyId);

        if (! $property) {
            return [
                'status' => 'error',
                'message' => 'Property not found',
            ];
        }

        $propertyData = [
            'property_id' => $property->id,
            'area' => $property->total_floor_area,
            'rooms' => $this->extractRoomCount($property->compartment_pattern),
            'latitude' => $property->latitude,
            'longitude' => $property->longitude,
            'district' => $property->district,
            'building_type' => $property->building_type,
            'rent_date' => $property->rent_date,
        ];

        return $this->predictRentPrice($propertyData);
    }

    public function batchPredict(array $properties): array
    {
        $results = [];

        foreach ($properties as $propertyData) {
            $results[] = $this->predictRentPrice($propertyData);
        }

        return [
            'status' => 'success',
            'predictions' => $results,
            'total_count' => count($results),
            'successful_count' => count(array_filter($results, fn ($r) => $r['status'] === 'success')),
        ];
    }

    private function preprocessFeatures(array $data, array $normalizationParams): array
    {
        $features = [
            'area' => (float) ($data['area'] ?? 0),
            'rooms' => (int) ($data['rooms'] ?? 1),
            'latitude' => (float) ($data['latitude'] ?? 0),
            'longitude' => (float) ($data['longitude'] ?? 0),
            'district_encoded' => $this->encodeDistrict($data['district'] ?? ''),
            'building_type_encoded' => $this->encodeBuildingType($data['building_type'] ?? ''),
            'age' => $this->calculateAge($data['rent_date'] ?? null),
            'price_per_sqm' => 0,
        ];

        // 計算每坪價格
        if ($features['area'] > 0) {
            $features['price_per_sqm'] = ($data['rent_per_month'] ?? 0) / $features['area'];
        }

        // 正規化特徵
        $normalizedFeatures = [];
        foreach ($features as $name => $value) {
            if (isset($normalizationParams[$name])) {
                $params = $normalizationParams[$name];
                $normalizedFeatures[$name] = $params['std'] > 0
                    ? ($value - $params['mean']) / $params['std']
                    : 0;
            } else {
                $normalizedFeatures[$name] = $value;
            }
        }

        return $normalizedFeatures;
    }

    private function executePrediction(object $model, array $features): float
    {
        $prediction = 0;

        foreach ($model->coefficients as $featureName => $coefficient) {
            if (isset($features[$featureName])) {
                $prediction += $coefficient * $features[$featureName];
            }
        }

        return max(0, $prediction);
    }

    private function calculateConfidence(object $model, array $features): float
    {
        // 基於特徵完整性和模型準確度計算信心度
        $featureCompleteness = $this->calculateFeatureCompleteness($features);
        $modelAccuracy = $this->getModelAccuracy();

        // 綜合信心度計算
        $confidence = ($featureCompleteness * 0.6) + ($modelAccuracy * 0.4);

        return min(1.0, max(0.1, $confidence));
    }

    private function calculatePredictionRange(float $prediction, float $confidence): array
    {
        // 基於信心度計算預測範圍
        $margin = (1 - $confidence) * 0.3; // 最高30%的誤差範圍

        return [
            'min' => $prediction * (1 - $margin),
            'max' => $prediction * (1 + $margin),
        ];
    }

    private function generateExplanations(object $model, array $features, float $prediction): array
    {
        $explanations = [];

        // 基於特徵重要性生成解釋
        $topFeatures = array_slice($model->feature_importance, 0, 3, true);

        foreach ($topFeatures as $feature => $importance) {
            $value = $features[$feature] ?? 0;
            $explanations[] = $this->getFeatureExplanation($feature, $value, $importance);
        }

        return $explanations;
    }

    private function getFeatureExplanation(string $feature, float $value, float $importance): string
    {
        $explanations = [
            'area' => "面積 {$value} 坪，影響預測結果 {$importance}%",
            'rooms' => "房間數 {$value} 間，影響預測結果 {$importance}%",
            'district_encoded' => "區域位置影響預測結果 {$importance}%",
            'building_type_encoded' => "建築類型影響預測結果 {$importance}%",
            'latitude' => "地理位置緯度影響預測結果 {$importance}%",
            'longitude' => "地理位置經度影響預測結果 {$importance}%",
            'age' => "物件年齡影響預測結果 {$importance}%",
            'price_per_sqm' => "每坪價格影響預測結果 {$importance}%",
        ];

        return $explanations[$feature] ?? "特徵 {$feature} 影響預測結果 {$importance}%";
    }

    private function getImportantFactors(object $model, array $features): array
    {
        $factors = [];

        foreach ($model->feature_importance as $feature => $importance) {
            if ($importance > 0.1) { // 只顯示重要性大於10%的特徵
                $factors[] = [
                    'feature' => $this->getFeatureDisplayName($feature),
                    'importance' => round($importance * 100, 2),
                    'value' => round($features[$feature] ?? 0, 2),
                ];
            }
        }

        return $factors;
    }

    private function getFeatureDisplayName(string $feature): string
    {
        $displayNames = [
            'area' => '面積',
            'rooms' => '房間數',
            'district_encoded' => '行政區',
            'building_type_encoded' => '建築類型',
            'latitude' => '緯度',
            'longitude' => '經度',
            'age' => '物件年齡',
            'price_per_sqm' => '每坪價格',
        ];

        return $displayNames[$feature] ?? $feature;
    }

    private function calculateFeatureCompleteness(array $features): float
    {
        $requiredFeatures = ['area', 'rooms', 'latitude', 'longitude', 'district_encoded'];
        $completedFeatures = 0;

        foreach ($requiredFeatures as $feature) {
            if (isset($features[$feature]) && $features[$feature] != 0) {
                $completedFeatures++;
            }
        }

        return $completedFeatures / count($requiredFeatures);
    }

    private function getModelAccuracy(): float
    {
        $modelInfo = $this->trainingService->getModelInfo();

        return $modelInfo['accuracy'] ?? 0.75; // 預設75%準確率
    }

    private function extractRoomCount(?string $pattern): int
    {
        if (! $pattern) {
            return 1;
        }

        if (preg_match('/(\d+)房/', $pattern, $matches)) {
            return (int) $matches[1];
        }

        return 1;
    }

    private function encodeDistrict(string $district): float
    {
        $districtMap = [
            '中正區' => 1.0, '大同區' => 2.0, '中山區' => 3.0, '松山區' => 4.0,
            '大安區' => 5.0, '萬華區' => 6.0, '信義區' => 7.0, '士林區' => 8.0,
            '北投區' => 9.0, '內湖區' => 10.0, '南港區' => 11.0, '文山區' => 12.0,
        ];

        return $districtMap[$district] ?? 0.0;
    }

    private function encodeBuildingType(?string $type): float
    {
        $typeMap = [
            '住宅大樓' => 1.0,
            '華廈' => 2.0,
            '公寓' => 3.0,
            '透天厝' => 4.0,
            '套房' => 5.0,
        ];

        return $typeMap[$type] ?? 0.0;
    }

    private function calculateAge(?string $rentDate): float
    {
        if (! $rentDate) {
            return 0.0;
        }

        try {
            $date = \Carbon\Carbon::parse($rentDate);
            $now = now();

            return $now->diffInMonths($date);
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    private function analyzeMarketTrends(?string $district = null): array
    {
        $query = Property::query()
            ->whereNotNull('rent_per_month')
            ->whereNotNull('total_floor_area')
            ->where('rent_per_month', '>', 0)
            ->where('total_floor_area', '>', 0);

        if ($district) {
            $query->where('district', $district);
        }

        $properties = $query->get();

        if ($properties->isEmpty()) {
            return [
                'trend' => 'stable',
                'average_price' => 0,
                'price_change' => 0,
                'volume' => 0,
            ];
        }

        // 計算平均價格和趨勢
        $averagePrice = $properties->avg('rent_per_month');
        $totalArea = $properties->sum('total_floor_area');
        $averagePricePerSqm = $totalArea > 0 ? ($properties->sum('rent_per_month') / $totalArea) : 0;

        // 簡單的趨勢分析（基於最近的資料）
        $recentProperties = $properties->sortByDesc('rent_date')->take(10);
        $olderProperties = $properties->sortBy('rent_date')->take(10);

        $recentAvg = $recentProperties->avg('rent_per_month');
        $olderAvg = $olderProperties->avg('rent_per_month');

        $priceChange = $olderAvg > 0 ? (($recentAvg - $olderAvg) / $olderAvg) * 100 : 0;

        $trend = $priceChange > 5 ? 'up' : ($priceChange < -5 ? 'down' : 'stable');

        return [
            'trend' => $trend,
            'average_price' => round($averagePrice, 2),
            'average_price_per_sqm' => round($averagePricePerSqm, 2),
            'price_change' => round($priceChange, 2),
            'volume' => $properties->count(),
            'district' => $district ?? 'all',
        ];
    }

    private function savePrediction(int $propertyId, float $predictedPrice, float $confidence, array $range, array $explanations, string $modelVersion): void
    {
        try {
            Prediction::create([
                'property_id' => $propertyId,
                'model_version' => $modelVersion,
                'predicted_price' => $predictedPrice,
                'confidence' => $confidence,
                'range_min' => $range['min'],
                'range_max' => $range['max'],
                'explanations' => $explanations,
                'metadata' => [
                    'prediction_date' => now()->toISOString(),
                    'model_type' => 'linear_regression',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save prediction', [
                'property_id' => $propertyId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
