<?php

namespace App\Services;

use App\Models\Anomaly;
use App\Models\Property;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AnomalyDetectionService
{
    private const ANOMALY_CACHE_TTL = 3600; // 1小時

    public function detectPriceAnomalies(): array
    {
        try {
            $anomalies = [];

            // 1. 統計分析異常檢測
            $statisticalAnomalies = $this->detectStatisticalAnomalies();

            // 2. 機器學習異常檢測
            $mlAnomalies = $this->detectMLAnomalies();

            // 3. 時間序列異常檢測
            $timeSeriesAnomalies = $this->detectTimeSeriesAnomalies();

            $anomalies = array_merge($statisticalAnomalies, $mlAnomalies, $timeSeriesAnomalies);

            // 4. 儲存異常記錄
            $this->saveAnomalies($anomalies);

            return [
                'status' => 'success',
                'anomalies' => $anomalies,
                'total_count' => count($anomalies),
                'detected_at' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('Anomaly detection failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Anomaly detection failed: '.$e->getMessage(),
            ];
        }
    }

    public function detectMarketAnomalies(): array
    {
        $cacheKey = 'market_anomalies';

        return Cache::remember($cacheKey, self::ANOMALY_CACHE_TTL, function () {
            return $this->analyzeMarketAnomalies();
        });
    }

    private function detectStatisticalAnomalies(): array
    {
        $anomalies = [];

        // Z-score 異常檢測
        $zScoreAnomalies = $this->detectZScoreAnomalies();

        // IQR 異常檢測
        $iqrAnomalies = $this->detectIQRAnomalies();

        return array_merge($zScoreAnomalies, $iqrAnomalies);
    }

    private function detectMLAnomalies(): array
    {
        // 簡化的機器學習異常檢測
        $properties = Property::whereNotNull('rent_per_month')
            ->whereNotNull('total_floor_area')
            ->where('rent_per_month', '>', 0)
            ->where('total_floor_area', '>', 0)
            ->get();

        $anomalies = [];

        foreach ($properties as $property) {
            $pricePerSqm = $property->rent_per_month / $property->total_floor_area;

            // 簡單的異常檢測邏輯
            if ($pricePerSqm > 3000 || $pricePerSqm < 500) {
                $anomalies[] = [
                    'property_id' => $property->id,
                    'category' => 'price_anomaly',
                    'severity' => $pricePerSqm > 3000 ? 'high' : 'medium',
                    'description' => "每坪價格異常：{$pricePerSqm} 元/坪",
                    'context' => [
                        'rent_price' => $property->rent_per_month,
                        'area' => $property->total_floor_area,
                        'price_per_sqm' => $pricePerSqm,
                        'district' => $property->district,
                    ],
                ];
            }
        }

        return $anomalies;
    }

    private function detectTimeSeriesAnomalies(): array
    {
        // 時間序列異常檢測
        return [];
    }

    private function detectZScoreAnomalies(): array
    {
        $properties = Property::whereNotNull('rent_per_month')
            ->whereNotNull('total_floor_area')
            ->where('rent_per_month', '>', 0)
            ->where('total_floor_area', '>', 0)
            ->get();

        $prices = $properties->pluck('rent_per_month')->toArray();

        if (count($prices) < 10) {
            return [];
        }

        $mean = array_sum($prices) / count($prices);
        $variance = array_sum(array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $prices)) / (count($prices) - 1);
        $stdDev = sqrt($variance);

        $anomalies = [];

        foreach ($properties as $property) {
            $zScore = $stdDev > 0 ? abs($property->rent_per_month - $mean) / $stdDev : 0;

            if ($zScore > 2.5) { // Z-score > 2.5 視為異常
                $anomalies[] = [
                    'property_id' => $property->id,
                    'category' => 'statistical_anomaly',
                    'severity' => $zScore > 3 ? 'high' : 'medium',
                    'description' => "價格統計異常：Z-score = {$zScore}",
                    'context' => [
                        'rent_price' => $property->rent_per_month,
                        'z_score' => $zScore,
                        'mean' => $mean,
                        'std_dev' => $stdDev,
                    ],
                ];
            }
        }

        return $anomalies;
    }

    private function detectIQRAnomalies(): array
    {
        $properties = Property::whereNotNull('rent_per_month')
            ->whereNotNull('total_floor_area')
            ->where('rent_per_month', '>', 0)
            ->where('total_floor_area', '>', 0)
            ->get();

        $prices = $properties->pluck('rent_per_month')->sort()->values()->toArray();

        if (count($prices) < 10) {
            return [];
        }

        $q1Index = intval(count($prices) * 0.25);
        $q3Index = intval(count($prices) * 0.75);

        $q1 = $prices[$q1Index];
        $q3 = $prices[$q3Index];
        $iqr = $q3 - $q1;

        $lowerBound = $q1 - 1.5 * $iqr;
        $upperBound = $q3 + 1.5 * $iqr;

        $anomalies = [];

        foreach ($properties as $property) {
            if ($property->rent_per_month < $lowerBound || $property->rent_per_month > $upperBound) {
                $anomalies[] = [
                    'property_id' => $property->id,
                    'category' => 'iqr_anomaly',
                    'severity' => 'medium',
                    'description' => '價格超出IQR範圍',
                    'context' => [
                        'rent_price' => $property->rent_per_month,
                        'lower_bound' => $lowerBound,
                        'upper_bound' => $upperBound,
                        'q1' => $q1,
                        'q3' => $q3,
                        'iqr' => $iqr,
                    ],
                ];
            }
        }

        return $anomalies;
    }

    private function analyzeMarketAnomalies(): array
    {
        // 市場異常分析
        $districts = Property::distinct()->pluck('district')->filter();
        $marketAnomalies = [];

        foreach ($districts as $district) {
            $districtProperties = Property::where('district', $district)
                ->whereNotNull('rent_per_month')
                ->where('rent_per_month', '>', 0)
                ->get();

            if ($districtProperties->count() < 5) {
                continue;
            }

            $avgPrice = $districtProperties->avg('rent_per_month');
            $priceStdDev = $this->calculateStandardDeviation($districtProperties->pluck('rent_per_month')->toArray());

            // 檢測該地區是否有異常高或低的價格
            $extremeProperties = $districtProperties->filter(function ($property) use ($avgPrice, $priceStdDev) {
                return abs($property->rent_per_month - $avgPrice) > 2 * $priceStdDev;
            });

            if ($extremeProperties->count() > 0) {
                $marketAnomalies[] = [
                    'district' => $district,
                    'category' => 'market_anomaly',
                    'severity' => 'medium',
                    'description' => "{$district} 市場價格異常，發現 {$extremeProperties->count()} 筆異常物件",
                    'context' => [
                        'district' => $district,
                        'average_price' => $avgPrice,
                        'price_std_dev' => $priceStdDev,
                        'anomaly_count' => $extremeProperties->count(),
                        'total_properties' => $districtProperties->count(),
                    ],
                ];
            }
        }

        return $marketAnomalies;
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

    private function saveAnomalies(array $anomalies): void
    {
        try {
            foreach ($anomalies as $anomaly) {
                Anomaly::create([
                    'property_id' => $anomaly['property_id'] ?? null,
                    'category' => $anomaly['category'],
                    'severity' => $anomaly['severity'],
                    'description' => $anomaly['description'],
                    'context' => $anomaly['context'] ?? [],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to save anomalies', [
                'error' => $e->getMessage(),
                'anomaly_count' => count($anomalies),
            ]);
        }
    }
}
