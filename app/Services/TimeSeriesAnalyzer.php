<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TimeSeriesAnalyzer
{
    private const ANALYSIS_CACHE_TTL = 3600; // 1小時

    public function analyzeRentTrends(?string $district = null, int $months = 12): array
    {
        $cacheKey = 'rent_trends_'.($district ?? 'all')."_{$months}";

        return Cache::remember($cacheKey, self::ANALYSIS_CACHE_TTL, function () use ($district, $months) {
            try {
                return $this->performTrendAnalysis($district, $months);
            } catch (\Exception $e) {
                Log::error('Rent trend analysis failed', [
                    'district' => $district,
                    'months' => $months,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'status' => 'error',
                    'message' => 'Trend analysis failed: '.$e->getMessage(),
                ];
            }
        });
    }

    public function predictFutureTrends(?string $district = null, int $forecastMonths = 6): array
    {
        try {
            $historicalData = $this->getHistoricalData($district, 12);

            if (empty($historicalData)) {
                return [
                    'status' => 'error',
                    'message' => 'Insufficient historical data for forecasting',
                ];
            }

            $forecast = $this->implementTimeSeriesForecasting($historicalData, $forecastMonths);

            return [
                'status' => 'success',
                'forecast' => $forecast,
                'confidence' => $this->calculateForecastConfidence($historicalData),
                'method' => 'linear_regression',
                'forecast_period' => $forecastMonths,
            ];

        } catch (\Exception $e) {
            Log::error('Future trend prediction failed', [
                'district' => $district,
                'forecast_months' => $forecastMonths,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Prediction failed: '.$e->getMessage(),
            ];
        }
    }

    public function detectSeasonalPatterns(?string $district = null): array
    {
        try {
            $historicalData = $this->getHistoricalData($district, 24);

            if (count($historicalData) < 12) {
                return [
                    'status' => 'error',
                    'message' => 'Insufficient data for seasonal analysis (need at least 12 months)',
                ];
            }

            $seasonalPatterns = $this->analyzeSeasonalPatterns($historicalData);

            return [
                'status' => 'success',
                'seasonal_patterns' => $seasonalPatterns,
                'seasonality_strength' => $this->calculateSeasonalityStrength($seasonalPatterns),
                'peak_months' => $this->identifyPeakMonths($seasonalPatterns),
                'low_months' => $this->identifyLowMonths($seasonalPatterns),
            ];

        } catch (\Exception $e) {
            Log::error('Seasonal pattern detection failed', [
                'district' => $district,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Seasonal analysis failed: '.$e->getMessage(),
            ];
        }
    }

    private function performTrendAnalysis(?string $district, int $months): array
    {
        $data = $this->getHistoricalData($district, $months);

        if (empty($data)) {
            return [
                'trend_direction' => 'stable',
                'trend_strength' => 0,
                'seasonal_components' => [],
                'cyclical_patterns' => [],
                'data_points' => 0,
            ];
        }

        return [
            'trend_direction' => $this->calculateTrendDirection($data),
            'trend_strength' => $this->calculateTrendStrength($data),
            'seasonal_components' => $this->extractSeasonalComponents($data),
            'cyclical_patterns' => $this->identifyCyclicalPatterns($data),
            'data_points' => count($data),
            'average_price' => array_sum($data) / count($data),
            'price_volatility' => $this->calculateVolatility($data),
        ];
    }

    private function getHistoricalData(?string $district, int $months): array
    {
        $query = Property::where('created_at', '>=', now()->subMonths($months));

        if ($district) {
            $query->where('district', $district);
        }

        $data = $query->orderBy('created_at')
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m');
            })
            ->map(function ($group) {
                return $group->avg('rent_per_month');
            })
            ->toArray();

        return array_values($data);
    }

    private function calculateTrendDirection(array $data): string
    {
        if (count($data) < 2) {
            return 'stable';
        }

        $firstHalf = array_slice($data, 0, intval(count($data) / 2));
        $secondHalf = array_slice($data, intval(count($data) / 2));

        $firstAvg = array_sum($firstHalf) / count($firstHalf);
        $secondAvg = array_sum($secondHalf) / count($secondHalf);

        $changePercent = $firstAvg > 0 ? (($secondAvg - $firstAvg) / $firstAvg) * 100 : 0;

        if ($changePercent > 5) {
            return 'up';
        } elseif ($changePercent < -5) {
            return 'down';
        } else {
            return 'stable';
        }
    }

    private function calculateTrendStrength(array $data): float
    {
        if (count($data) < 2) {
            return 0;
        }

        $x = range(1, count($data));
        $y = $data;

        $slope = $this->calculateLinearRegressionSlope($x, $y);

        return min(1.0, abs($slope) / 1000);
    }

    private function calculateVolatility(array $data): float
    {
        if (count($data) < 2) {
            return 0;
        }

        $mean = array_sum($data) / count($data);
        $variance = array_sum(array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $data)) / count($data);

        $stdDev = sqrt($variance);

        return $mean > 0 ? $stdDev / $mean : 0;
    }

    private function calculateLinearRegressionSlope(array $x, array $y): float
    {
        if (count($x) != count($y) || count($x) < 2) {
            return 0;
        }

        $n = count($x);
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
        }

        $denominator = $n * $sumX2 - $sumX * $sumX;

        return $denominator != 0 ? ($n * $sumXY - $sumX * $sumY) / $denominator : 0;
    }

    private function extractSeasonalComponents(array $data): array
    {
        if (count($data) < 12) {
            return [];
        }

        $monthlyAverages = [];

        for ($i = 0; $i < 12; $i++) {
            $monthData = [];
            for ($j = $i; $j < count($data); $j += 12) {
                if (isset($data[$j])) {
                    $monthData[] = $data[$j];
                }
            }

            if (! empty($monthData)) {
                $monthlyAverages[$i + 1] = array_sum($monthData) / count($monthData);
            }
        }

        return $monthlyAverages;
    }

    private function identifyCyclicalPatterns(array $data): array
    {
        if (count($data) < 24) {
            return [];
        }

        $patterns = [];

        $yearlyCycle = $this->detectYearlyCycle($data);
        if ($yearlyCycle > 0.3) {
            $patterns[] = [
                'type' => 'yearly',
                'strength' => $yearlyCycle,
                'period' => 12,
            ];
        }

        return $patterns;
    }

    private function implementTimeSeriesForecasting(array $data, int $forecastMonths): array
    {
        if (count($data) < 6) {
            return [];
        }

        $forecast = [];
        $x = range(1, count($data));
        $y = $data;

        $slope = $this->calculateLinearRegressionSlope($x, $y);
        $intercept = $this->calculateLinearRegressionIntercept($x, $y, $slope);

        for ($i = 1; $i <= $forecastMonths; $i++) {
            $forecastValue = $intercept + $slope * (count($data) + $i);
            $forecast[] = max(0, $forecastValue);
        }

        return $forecast;
    }

    private function calculateForecastConfidence(array $data): float
    {
        if (count($data) < 6) {
            return 0.5;
        }

        $volatility = $this->calculateVolatility($data);
        $dataQuality = min(1.0, count($data) / 24);

        $confidence = (1 - $volatility) * $dataQuality;

        return max(0.3, min(0.9, $confidence));
    }

    private function calculateLinearRegressionIntercept(array $x, array $y, float $slope): float
    {
        if (count($x) != count($y) || count($x) == 0) {
            return 0;
        }

        $meanX = array_sum($x) / count($x);
        $meanY = array_sum($y) / count($y);

        return $meanY - $slope * $meanX;
    }

    private function detectYearlyCycle(array $data): float
    {
        if (count($data) < 12) {
            return 0;
        }

        return $this->calculateAutocorrelation($data, 12);
    }

    private function calculateAutocorrelation(array $data, int $lag): float
    {
        if (count($data) <= $lag) {
            return 0;
        }

        $n = count($data) - $lag;
        $mean = array_sum($data) / count($data);

        $numerator = 0;
        $denominator = 0;

        for ($i = 0; $i < $n; $i++) {
            $numerator += ($data[$i] - $mean) * ($data[$i + $lag] - $mean);
            $denominator += pow($data[$i] - $mean, 2);
        }

        return $denominator > 0 ? $numerator / $denominator : 0;
    }

    private function analyzeSeasonalPatterns(array $data): array
    {
        $seasonalIndexes = [];

        for ($month = 1; $month <= 12; $month++) {
            $monthData = [];
            for ($i = $month - 1; $i < count($data); $i += 12) {
                if (isset($data[$i])) {
                    $monthData[] = $data[$i];
                }
            }

            if (! empty($monthData)) {
                $monthAvg = array_sum($monthData) / count($monthData);
                $overallAvg = array_sum($data) / count($data);
                $seasonalIndexes[$month] = $overallAvg > 0 ? $monthAvg / $overallAvg : 1.0;
            }
        }

        return $seasonalIndexes;
    }

    private function calculateSeasonalityStrength(array $seasonalPatterns): float
    {
        if (empty($seasonalPatterns)) {
            return 0;
        }

        $values = array_values($seasonalPatterns);
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(function ($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / count($values);

        $stdDev = sqrt($variance);

        return $mean > 0 ? $stdDev / $mean : 0;
    }

    private function identifyPeakMonths(array $seasonalPatterns): array
    {
        if (empty($seasonalPatterns)) {
            return [];
        }

        arsort($seasonalPatterns);

        return array_slice(array_keys($seasonalPatterns), 0, 3);
    }

    private function identifyLowMonths(array $seasonalPatterns): array
    {
        if (empty($seasonalPatterns)) {
            return [];
        }

        asort($seasonalPatterns);

        return array_slice(array_keys($seasonalPatterns), 0, 3);
    }
}
