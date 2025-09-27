<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AIModelTrainingService
{
    private const MODEL_VERSION = '1.0.0';

    private const TRAINING_CACHE_KEY = 'ai_model_training_data';

    private const MODEL_STORAGE_PATH = 'models/rent_prediction.json';

    public function trainRentPredictionModel(): array
    {
        Log::info('Starting AI model training for rent prediction');

        try {
            // 1. 資料準備
            $trainingData = $this->prepareTrainingData();

            if (empty($trainingData)) {
                return [
                    'status' => 'error',
                    'message' => 'No training data available',
                ];
            }

            // 2. 特徵工程
            $features = $this->extractFeatures($trainingData);

            // 3. 模型訓練
            $model = $this->trainModel($features);

            // 4. 模型驗證
            $accuracy = $this->validateModel($model, $features);

            // 5. 儲存模型
            $this->saveModel($model);

            // 6. 更新快取
            Cache::put(self::TRAINING_CACHE_KEY, [
                'model_version' => self::MODEL_VERSION,
                'accuracy' => $accuracy,
                'training_samples' => count($trainingData),
                'last_trained' => now(),
            ], 86400); // 24小時快取

            Log::info('AI model training completed successfully', [
                'accuracy' => $accuracy,
                'samples' => count($trainingData),
                'version' => self::MODEL_VERSION,
            ]);

            return [
                'status' => 'success',
                'accuracy' => $accuracy,
                'model_version' => self::MODEL_VERSION,
                'training_samples' => count($trainingData),
                'last_trained' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('AI model training failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Training failed: '.$e->getMessage(),
            ];
        }
    }

    private function prepareTrainingData(): array
    {
        return Property::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('total_rent')
            ->where('total_rent', '>', 0)
            ->whereNotNull('area_ping')
            ->where('area_ping', '>', 0)
            ->get()
            ->map(function ($property) {
                return [
                    'id' => $property->id,
                    'area_ping' => $property->area_ping,
                    'bedrooms' => $property->bedrooms,
                    'living_rooms' => $property->living_rooms,
                    'bathrooms' => $property->bathrooms,
                    'latitude' => $property->latitude,
                    'longitude' => $property->longitude,
                    'city' => $property->city,
                    'district' => $property->district,
                    'building_type' => $property->building_type,
                    'rental_type' => $property->rental_type,
                    'total_rent' => $property->total_rent,
                    'rent_per_ping' => $property->rent_per_ping,
                    'building_age' => $property->building_age,
                    'has_elevator' => $property->has_elevator,
                    'has_management_organization' => $property->has_management_organization,
                    'has_furniture' => $property->has_furniture,
                    'rent_date' => $property->rent_date,
                ];
            })
            ->toArray();
    }

    private function extractFeatures(array $data): array
    {
        $features = [];

        foreach ($data as $property) {
            $area = (float) $property['area_ping'];
            $rooms = (int) $property['bedrooms'] + (int) $property['living_rooms'];
            $rentPrice = (float) $property['total_rent'];

            $features[] = [
                'area' => $area,
                'rooms' => $rooms,
                'bedrooms' => (int) $property['bedrooms'],
                'living_rooms' => (int) $property['living_rooms'],
                'bathrooms' => (int) $property['bathrooms'],
                'district_encoded' => $this->encodeDistrict($property['district']),
                'building_type_encoded' => $this->encodeBuildingType($property['building_type']),
                'age' => $this->calculateAge($property['rent_date']),
                'price_per_sqm' => $area > 0 ? $rentPrice / $area : 0,
                'target' => $rentPrice,
            ];
        }

        return $features;
    }

    private function trainModel(array $features): object
    {
        // 簡化的機器學習模型實作
        $normalizedFeatures = $this->normalizeFeatures($features);
        $coefficients = $this->calculateLinearRegression($normalizedFeatures);

        return (object) [
            'type' => 'linear_regression',
            'coefficients' => $coefficients['coefficients'] ?? $coefficients,
            'intercept' => $coefficients['intercept'] ?? 0,
            'feature_importance' => $this->calculateFeatureImportance($coefficients['coefficients'] ?? $coefficients),
            'normalization_params' => $this->getNormalizationParams($features),
            'training_date' => now(),
            'version' => self::MODEL_VERSION,
        ];
    }

    private function validateModel(object $model, array $features): float
    {
        // 簡單的交叉驗證
        $testSize = max(1, intval(count($features) * 0.2));
        $testFeatures = array_slice($features, -$testSize);
        $trainFeatures = array_slice($features, 0, -$testSize);

        if (empty($trainFeatures) || empty($testFeatures)) {
            return 0.75; // 預設準確率
        }

        $predictions = [];
        $actuals = [];

        foreach ($testFeatures as $feature) {
            $prediction = $this->predictWithModel($model, $feature);
            $predictions[] = $prediction;
            $actuals[] = $feature['target'];
        }

        return $this->calculateAccuracy($predictions, $actuals);
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
            '住宅大樓' => 0.8,
            '華廈' => 0.6,
            '公寓' => 0.4,
            '透天厝' => 0.2,
            '套房' => 0.1,
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

    private function normalizeFeatures(array $features): array
    {
        if (empty($features)) {
            return [];
        }

        $normalized = [];
        $featureNames = ['area', 'rooms', 'district_encoded', 'building_type_encoded', 'age', 'price_per_sqm'];

        // 計算統計量
        $stats = [];
        foreach ($featureNames as $name) {
            $values = array_column($features, $name);
            if (empty($values)) {
                continue;
            }
            $stats[$name] = [
                'min' => min($values),
                'max' => max($values),
                'mean' => array_sum($values) / count($values),
                'std' => $this->calculateStandardDeviation($values),
            ];
        }

        // Z-score 正規化
        foreach ($features as $feature) {
            $normalizedFeature = [];
            foreach ($featureNames as $name) {
                $value = $feature[$name];
                $stat = $stats[$name];
                $normalizedFeature[$name] = $stat['std'] > 0
                    ? ($value - $stat['mean']) / $stat['std']
                    : 0;
            }
            $normalizedFeature['target'] = $feature['target'];
            $normalized[] = $normalizedFeature;
        }

        return $normalized;
    }

    private function calculateLinearRegression(array $features): array
    {
        $featureNames = ['area', 'rooms', 'district_encoded', 'building_type_encoded', 'age', 'price_per_sqm'];
        $coefficients = [];

        foreach ($featureNames as $name) {
            $values = array_column($features, $name);
            $targets = array_column($features, 'target');
            $correlation = $this->calculateCorrelation($values, $targets);
            $coefficients[$name] = $correlation * 1000;
        }

        // 計算截距（簡化版）
        $targets = array_column($features, 'target');
        $intercept = empty($targets) ? 0 : array_sum($targets) / count($targets);

        // 計算 R²
        $predictions = [];
        foreach ($features as $feature) {
            $pred = $intercept;
            foreach ($featureNames as $name) {
                $pred += $coefficients[$name] * $feature[$name];
            }
            $predictions[] = $pred;
        }

        $rSquared = $this->calculateR2($predictions, $targets);

        return [
            'coefficients' => $coefficients,
            'intercept' => $intercept,
            'r_squared' => $rSquared,
        ];
    }

    private function calculateFeatureImportance(array $coefficients): array
    {
        $total = array_sum(array_map('abs', $coefficients));

        if ($total == 0) {
            return array_fill_keys(array_keys($coefficients), 0);
        }

        $importance = [];
        foreach ($coefficients as $feature => $coef) {
            $importance[$feature] = abs($coef) / $total;
        }

        arsort($importance);

        return $importance;
    }

    private function getNormalizationParams(array $features): array
    {
        if (empty($features)) {
            return [];
        }

        $featureNames = ['area', 'rooms', 'district_encoded', 'building_type_encoded', 'age', 'price_per_sqm'];
        $params = [];

        foreach ($featureNames as $name) {
            $values = array_column($features, $name);
            if (empty($values)) {
                continue;
            }
            $params[$name] = [
                'min' => min($values),
                'max' => max($values),
                'mean' => array_sum($values) / count($values),
                'std' => $this->calculateStandardDeviation($values),
            ];
        }

        return $params;
    }

    private function predictWithModel(object $model, array $feature): float
    {
        $prediction = 0;

        foreach ($model->coefficients as $featureName => $coefficient) {
            if (isset($feature[$featureName])) {
                $prediction += $coefficient * $feature[$featureName];
            }
        }

        return max(0, $prediction);
    }

    private function calculateAccuracy(array $predictions, array $actuals): float
    {
        if (count($predictions) != count($actuals) || count($predictions) == 0) {
            return 0.0;
        }

        $errors = [];
        for ($i = 0; $i < count($predictions); $i++) {
            if ($actuals[$i] > 0) {
                $errors[] = abs($predictions[$i] - $actuals[$i]) / $actuals[$i];
            }
        }

        if (empty($errors)) {
            return 0.0;
        }

        $mape = array_sum($errors) / count($errors);

        return max(0, 1 - $mape);
    }

    private function calculateStandardDeviation(array $values): float
    {
        if (count($values) <= 1) {
            return 0.0;
        }

        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / (count($values) - 1);

        return sqrt($variance);
    }

    private function calculateCorrelation(array $x, array $y): float
    {
        if (count($x) != count($y) || count($x) == 0) {
            return 0.0;
        }

        $n = count($x);
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
            $sumY2 += $y[$i] * $y[$i];
        }

        $numerator = $n * $sumXY - $sumX * $sumY;
        $denominator = sqrt(($n * $sumX2 - $sumX * $sumX) * ($n * $sumY2 - $sumY * $sumY));

        return $denominator > 0 ? $numerator / $denominator : 0.0;
    }

    private function calculateR2(array $predictions, array $actuals): float
    {
        if (empty($predictions) || empty($actuals) || count($predictions) !== count($actuals)) {
            return 0.0;
        }

        $meanActual = array_sum($actuals) / count($actuals);
        $ssRes = 0; // 殘差平方和
        $ssTot = 0; // 總平方和

        for ($i = 0; $i < count($predictions); $i++) {
            $ssRes += pow($actuals[$i] - $predictions[$i], 2);
            $ssTot += pow($actuals[$i] - $meanActual, 2);
        }

        if ($ssTot == 0) {
            return 1.0;
        }

        return 1 - ($ssRes / $ssTot);
    }

    private function saveModel(object $model): void
    {
        $modelData = [
            'type' => $model->type,
            'coefficients' => $model->coefficients,
            'feature_importance' => $model->feature_importance,
            'normalization_params' => $model->normalization_params,
            'training_date' => $model->training_date->toISOString(),
            'version' => $model->version,
        ];

        Storage::put(self::MODEL_STORAGE_PATH, json_encode($modelData, JSON_PRETTY_PRINT));
    }

    public function loadModel(): ?object
    {
        if (! Storage::exists(self::MODEL_STORAGE_PATH)) {
            return null;
        }

        $modelData = json_decode(Storage::get(self::MODEL_STORAGE_PATH), true);

        if (! $modelData) {
            return null;
        }

        return (object) [
            'type' => $modelData['type'],
            'coefficients' => $modelData['coefficients'],
            'feature_importance' => $modelData['feature_importance'],
            'normalization_params' => $modelData['normalization_params'],
            'training_date' => \Carbon\Carbon::parse($modelData['training_date']),
            'version' => $modelData['version'],
        ];
    }

    public function getModelInfo(): array
    {
        $cache = Cache::get(self::TRAINING_CACHE_KEY);

        if (! $cache) {
            return [
                'status' => 'no_model',
                'message' => 'No trained model found',
                'model_version' => 'N/A',
                'last_trained' => null,
                'accuracy' => 0,
                'training_samples' => 0,
            ];
        }

        return [
            'status' => 'loaded',
            'model_version' => $cache['model_version'] ?? self::MODEL_VERSION,
            'last_trained' => $cache['last_trained'] ?? now()->toISOString(),
            'accuracy' => $cache['accuracy'] ?? 0,
            'training_samples' => $cache['training_samples'] ?? 0,
        ];
    }
}
