<?php

namespace App\Services;

use App\Support\AdvancedPricePredictor;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIMapOptimizationService
{
    private const CLUSTER_CACHE_TTL_SECONDS = 60;
    private const MAX_KMEANS_ITERATIONS = 15;
    private const CENTER_SHIFT_THRESHOLD_KM = 0.025; // 25 公尺內視為收斂

    private ?AdvancedPricePredictor $pricePredictor = null;

    /**
     * 智慧標記聚合演算法 (PHP 實現)
     */
    public function clusteringAlgorithm(array $data, string $algorithm = 'kmeans', int $nClusters = 10): array
    {
        $startedAt = microtime(true);
        $baselineMemory = memory_get_usage(true);

        try {
            $coordinates = $this->normalizeCoordinatePayload($data);
            if ($coordinates === []) {
                return [
                    'success' => false,
                    'error' => 'No valid coordinates provided for clustering.'
                ];
            }

            $nClusters = max(1, min($nClusters, count($coordinates)));
            $cacheKey = $this->buildClusterCacheKey($coordinates, $algorithm, $nClusters);

            $cached = Cache::get($cacheKey);
            if ($cached) {
                $clusters = $cached['clusters'];
                $quality = $cached['quality'];
                $cacheHit = true;
            } else {
                if ($algorithm === 'kmeans') {
                    $clusters = $this->kmeansClustering($coordinates, $nClusters);
                } else {
                    $clusters = $this->gridClustering($coordinates);
                }

                $quality = $this->summarizeClusters($clusters);
                Cache::put($cacheKey, [
                    'clusters' => $clusters,
                    'quality' => $quality,
                ], self::CLUSTER_CACHE_TTL_SECONDS);
                $cacheHit = false;
            }

            $processingTimeMs = (microtime(true) - $startedAt) * 1000;
            $memoryDeltaMb = (memory_get_peak_usage(true) - $baselineMemory) / 1048576;

            return [
                'success' => true,
                'clusters' => $clusters,
                'algorithm_info' => [
                    'type' => $algorithm,
                    'parameters' => [
                        'n_clusters' => $nClusters,
                        'points' => count($coordinates),
                    ],
                    'performance' => [
                        'processing_time_ms' => round($processingTimeMs, 2),
                        'memory_peak_mb' => max(0, round($memoryDeltaMb, 3)),
                        'cache_hit' => $cacheHit,
                    ],
                    'quality' => $quality,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Clustering algorithm error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * K-means 聚合演算法 (簡化版)
     */
    protected function kmeansClustering(array $coordinates, int $k): array
    {
        $pointCount = count($coordinates);
        if ($pointCount === 0) {
            return [];
        }

        $k = max(1, min($k, $pointCount));
        $centers = $this->initializeCentroids($coordinates, $k);
        $centerRadians = array_map(function (array $center): array {
            return [deg2rad($center[0]), deg2rad($center[1])];
        }, $centers);

        $latRadians = [];
        $lngRadians = [];
        for ($index = 0; $index < $pointCount; $index++) {
            $latRadians[$index] = deg2rad($coordinates[$index][0]);
            $lngRadians[$index] = deg2rad($coordinates[$index][1]);
        }

        $assignments = array_fill(0, $pointCount, -1);
        $clusters = [];

        for ($iteration = 0; $iteration < self::MAX_KMEANS_ITERATIONS; $iteration++) {
            $newCenters = array_fill(0, $k, [0.0, 0.0]);
            $counts = array_fill(0, $k, 0);
            $errors = array_fill(0, $pointCount, 0.0);
            $assignmentChanged = false;

            for ($idx = 0; $idx < $pointCount; $idx++) {
                $minDistance = PHP_FLOAT_MAX;
                $closestCluster = 0;

                for ($candidate = 0; $candidate < $k; $candidate++) {
                    $distance = $this->haversineFromRadians(
                        $latRadians[$idx],
                        $lngRadians[$idx],
                        $centerRadians[$candidate][0],
                        $centerRadians[$candidate][1]
                    );

                    if ($distance < $minDistance) {
                        $minDistance = $distance;
                        $closestCluster = $candidate;
                    }
                }

                if ($assignments[$idx] !== $closestCluster) {
                    $assignmentChanged = true;
                    $assignments[$idx] = $closestCluster;
                }

                $errors[$idx] = $minDistance;
                $newCenters[$closestCluster][0] += $coordinates[$idx][0];
                $newCenters[$closestCluster][1] += $coordinates[$idx][1];
                $counts[$closestCluster]++;
            }

            $maxShift = 0.0;
            for ($clusterIndex = 0; $clusterIndex < $k; $clusterIndex++) {
                if ($counts[$clusterIndex] === 0) {
                    $reseedCandidate = $this->maxErrorIndex($errors, $assignments, $clusterIndex);
                    if ($reseedCandidate !== null) {
                        $reseedIndex = $reseedCandidate['index'];
                        $previousCluster = $reseedCandidate['previous_cluster'];

                        if ($previousCluster !== null && $previousCluster >= 0) {
                            $counts[$previousCluster] = max(0, $counts[$previousCluster] - 1);
                            $newCenters[$previousCluster][0] -= $coordinates[$reseedIndex][0];
                            $newCenters[$previousCluster][1] -= $coordinates[$reseedIndex][1];
                        }

                        $centers[$clusterIndex] = $coordinates[$reseedIndex];
                        $centerRadians[$clusterIndex] = [
                            $latRadians[$reseedIndex],
                            $lngRadians[$reseedIndex],
                        ];
                        $assignments[$reseedIndex] = $clusterIndex;
                        $newCenters[$clusterIndex] = $coordinates[$reseedIndex];
                        $counts[$clusterIndex] = 1;
                        $errors[$reseedIndex] = 0.0;
                        $assignmentChanged = true;
                        continue;
                    }
                    continue;
                }

                $newLat = $newCenters[$clusterIndex][0] / $counts[$clusterIndex];
                $newLng = $newCenters[$clusterIndex][1] / $counts[$clusterIndex];
                $shift = $this->haversineFromRadians(
                    $centerRadians[$clusterIndex][0],
                    $centerRadians[$clusterIndex][1],
                    deg2rad($newLat),
                    deg2rad($newLng)
                );

                $centers[$clusterIndex] = [$newLat, $newLng];
                $centerRadians[$clusterIndex] = [deg2rad($newLat), deg2rad($newLng)];
                $maxShift = max($maxShift, $shift);
            }

            if (!$assignmentChanged && $maxShift <= self::CENTER_SHIFT_THRESHOLD_KM) {
                break;
            }
        }

        $members = array_fill(0, $k, []);
        for ($idx = 0; $idx < $pointCount; $idx++) {
            $clusterIndex = $assignments[$idx];
            if ($clusterIndex < 0) {
                continue;
            }
            $members[$clusterIndex][] = $coordinates[$idx];
        }

        foreach ($members as $index => $clusterPoints) {
            if ($clusterPoints === []) {
                continue;
            }

            $clusters[] = $this->formatClusterPayload($index, $centers[$index], $clusterPoints);
        }

        return $clusters;
    }

    /**
     * 網格聚合演算法
     */
    protected function gridClustering(array $coordinates): array
    {
        if (empty($coordinates)) {
            return [];
        }

        // 計算邊界
        $lats = array_column($coordinates, 0);
        $lngs = array_column($coordinates, 1);
        
        $minLat = min($lats);
        $maxLat = max($lats);
        $minLng = min($lngs);
        $maxLng = max($lngs);

        // 建立網格
        $rangeLat = max(0.0001, $maxLat - $minLat);
        $rangeLng = max(0.0001, $maxLng - $minLng);
        $range = max($rangeLat, $rangeLng);
        $gridSize = $this->resolveGridSize($range, count($coordinates));

        $grid = [];
        foreach ($coordinates as $point) {
            $latIndex = floor(($point[0] - $minLat) / $gridSize);
            $lngIndex = floor(($point[1] - $minLng) / $gridSize);
            $key = "{$latIndex}_{$lngIndex}";
            
            if (!isset($grid[$key])) {
                $grid[$key] = [];
            }
            $grid[$key][] = $point;
        }

        // 建立聚合
        $clusters = [];
        $clusterId = 0;
        foreach ($grid as $cell => $points) {
            if (count($points) > 0) {
                $centerLat = array_sum(array_column($points, 0)) / count($points);
                $centerLng = array_sum(array_column($points, 1)) / count($points);

                $clusters[] = $this->formatClusterPayload($clusterId, [$centerLat, $centerLng], $points);
                $clusterId++;
            }
        }

        return $clusters;
    }

    /**
     * 熱力圖分析 (PHP 實現)
     */
    public function generateHeatmap(array $data, string $resolution = 'medium'): array
    {
        try {
            $heatmapPoints = [];
            
            foreach ($data as $item) {
                $price = $item['price'] ?? 0;
                $weight = min($price / 50000, 1.0); // 標準化權重
                
                $heatmapPoints[] = [
                    'lat' => $item['lat'],
                    'lng' => $item['lng'],
                    'weight' => $weight,
                    'price_range' => $this->getPriceRange($price)
                ];
            }

            return [
                'success' => true,
                'heatmap_points' => $heatmapPoints,
                'color_scale' => [
                    'min' => 0.1,
                    'max' => 1.0,
                    'colors' => ['#00ff00', '#ffff00', '#ff0000']
                ],
                'statistics' => [
                    'total_points' => count($heatmapPoints),
                    'density_range' => ['min' => 0.1, 'max' => 1.0]
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Heatmap generation error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 價格預測 (進階啟發式模型)
     */
    public function predictPrices(array $data, array $options = []): array
    {
        $startedAt = microtime(true);
        $baselineMemory = memory_get_usage(true);

        try {
            $payload = [];
            foreach ($data as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $item['index'] = $item['index'] ?? $index;
                $payload[] = $item;
            }

            $predictor = $this->pricePredictor();
            $predictions = $predictor->predictCollection($payload, $options);

            $confidenceThreshold = isset($options['confidence_threshold'])
                ? $this->sanitizeConfidenceThreshold($options['confidence_threshold'])
                : null;

            $alerts = [];
            if ($confidenceThreshold !== null) {
                foreach ($predictions as $prediction) {
                    if (($prediction['confidence'] ?? 0) < $confidenceThreshold) {
                        $alerts[] = [
                            'index' => $prediction['index'],
                            'id' => $prediction['id'] ?? null,
                            'confidence' => $prediction['confidence'],
                            'threshold' => $confidenceThreshold,
                        ];
                    }
                }
            }

            $summary = $predictor->summarize($predictions);

            $processingTimeMs = (microtime(true) - $startedAt) * 1000;
            $memoryDeltaMb = (memory_get_peak_usage(true) - $baselineMemory) / 1048576;

            return [
                'success' => true,
                'data' => [
                    'predictions' => [
                        'items' => $predictions,
                        'summary' => $summary,
                    ],
                    'model_info' => [
                        'version' => AdvancedPricePredictor::MODEL_VERSION,
                        'trained_at' => AdvancedPricePredictor::TRAINED_AT,
                        'feature_set' => AdvancedPricePredictor::FEATURE_SET,
                    ],
                    'performance_metrics' => [
                        'processing_time_ms' => round($processingTimeMs, 2),
                        'memory_peak_mb' => round(max(0, $memoryDeltaMb), 4),
                        'predictions_per_second' => $processingTimeMs > 0
                            ? round((count($predictions) / $processingTimeMs) * 1000, 2)
                            : count($predictions),
                    ],
                    'alerts' => $alerts,
                ],
                'predictions' => [
                    'items' => $predictions,
                    'summary' => $summary,
                ],
                'model_info' => [
                    'version' => AdvancedPricePredictor::MODEL_VERSION,
                    'trained_at' => AdvancedPricePredictor::TRAINED_AT,
                    'feature_set' => AdvancedPricePredictor::FEATURE_SET,
                ],
                'performance_metrics' => [
                    'processing_time_ms' => round($processingTimeMs, 2),
                    'memory_peak_mb' => round(max(0, $memoryDeltaMb), 4),
                    'predictions_per_second' => $processingTimeMs > 0
                        ? round((count($predictions) / $processingTimeMs) * 1000, 2)
                        : count($predictions),
                ],
                'alerts' => $alerts,
            ];

        } catch (\Exception $e) {
            Log::error('Price prediction error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function predictPrice(array $data, array $options = []): array
    {
        $prediction = $this->predictPrices([$data], $options);
        $items = $prediction['predictions']['items'] ?? [];
        return $items[0] ?? [];
    }

    /**
     * 計算距離
     */
    protected function calculateDistance(array $point1, array $point2): float
    {
        $lat1 = $point1[0];
        $lng1 = $point1[1];
        $lat2 = $point2[0];
        $lng2 = $point2[1];

        $earthRadius = 6371; // 地球半徑 (km)
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }

    /**
     * 計算邊界
     */
    protected function calculateBounds(array $points): array
    {
        if (empty($points)) {
            return ['north' => 0, 'south' => 0, 'east' => 0, 'west' => 0];
        }

        $lats = array_column($points, 0);
        $lngs = array_column($points, 1);

        return [
            'north' => max($lats),
            'south' => min($lats),
            'east' => max($lngs),
            'west' => min($lngs),
        ];
    }

    /**
     * 取得價格區間標籤
     */
    protected function getPriceRange(float $price): string
    {
        if ($price < 20000) return '20000以下';
        if ($price < 30000) return '20000-30000';
        if ($price < 40000) return '30000-40000';
        return '40000以上';
    }

    /**
     * 取得位置因子
     */
    protected function getLocationFactor(float $lat, float $lng): float
    {
        // 簡化的位置因子計算
        // 台北市中心 (25.0330, 121.5654) 為基準
        $centerLat = 25.0330;
        $centerLng = 121.5654;
        
        $distance = $this->calculateDistance([$lat, $lng], [$centerLat, $centerLng]);

        // 距離市中心越近，價格因子越高
        return max(0, 10000 - $distance * 1000);
    }

    protected function pricePredictor(): AdvancedPricePredictor
    {
        if ($this->pricePredictor === null) {
            $this->pricePredictor = new AdvancedPricePredictor();
        }

        return $this->pricePredictor;
    }

    protected function normalizeCoordinatePayload(array $data): array
    {
        $coordinates = [];
        foreach ($data as $item) {
            $lat = Arr::get($item, 'lat', Arr::get($item, 'latitude'));
            $lng = Arr::get($item, 'lng', Arr::get($item, 'longitude'));

            if (!is_numeric($lat) || !is_numeric($lng)) {
                continue;
            }

            $coordinates[] = [
                (float) $lat,
                (float) $lng,
            ];
        }

        return $coordinates;
    }

    protected function buildClusterCacheKey(array $coordinates, string $algorithm, int $k): string
    {
        $signatureParts = [];
        foreach ($coordinates as $point) {
            $signatureParts[] = sprintf('%.4f:%.4f', $point[0], $point[1]);
        }

        return sprintf(
            'ai_map:clusters:%s:%d:%s',
            $algorithm,
            $k,
            md5(implode('|', $signatureParts))
        );
    }

    protected function haversineFromRadians(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = $lat2 - $lat1;
        $dLng = $lng2 - $lng1;

        $a = sin($dLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    protected function initializeCentroids(array $coordinates, int $k): array
    {
        $sorted = $coordinates;
        usort($sorted, static function (array $a, array $b): int {
            return $a[0] <=> $b[0] ?: $a[1] <=> $b[1];
        });

        $step = max(1, (int) floor(count($sorted) / $k));
        $centers = [];
        for ($i = 0; $i < $k; $i++) {
            $index = min($i * $step, count($sorted) - 1);
            $centers[] = $sorted[$index];
        }

        return $centers;
    }

    protected function maxErrorIndex(array $errors, array $assignments, int $clusterIndex): ?array
    {
        $maxError = -1.0;
        $maxIndex = null;
        $previousCluster = null;

        foreach ($errors as $index => $error) {
            if ($assignments[$index] === $clusterIndex) {
                continue;
            }

            if ($error > $maxError) {
                $maxError = $error;
                $maxIndex = $index;
                $previousCluster = $assignments[$index];
            }
        }

        if ($maxIndex === null) {
            return null;
        }

        return [
            'index' => $maxIndex,
            'previous_cluster' => $previousCluster,
        ];
    }

    protected function formatClusterPayload(int $clusterId, array $center, array $points): array
    {
        $bounds = $this->calculateBounds($points);
        $radius = $this->calculateClusterRadius($center, $points);
        $density = $radius > 0 ? count($points) / (M_PI * $radius ** 2) : null;

        return [
            'id' => "cluster_{$clusterId}",
            'center' => [
                'lat' => round($center[0], 6),
                'lng' => round($center[1], 6),
            ],
            'count' => count($points),
            'bounds' => $bounds,
            'radius_km' => round($radius, 4),
            'density' => $density !== null ? round($density, 4) : null,
        ];
    }

    protected function calculateClusterRadius(array $center, array $points): float
    {
        if ($points === []) {
            return 0.0;
        }

        $latCenter = deg2rad($center[0]);
        $lngCenter = deg2rad($center[1]);
        $maxDistance = 0.0;

        foreach ($points as $point) {
            $distance = $this->haversineFromRadians(
                $latCenter,
                $lngCenter,
                deg2rad($point[0]),
                deg2rad($point[1])
            );
            $maxDistance = max($maxDistance, $distance);
        }

        return $maxDistance;
    }

    protected function summarizeClusters(array $clusters): array
    {
        if ($clusters === []) {
            return [
                'cluster_count' => 0,
                'avg_radius_km' => 0.0,
                'median_density' => 0.0,
            ];
        }

        $radii = array_column($clusters, 'radius_km');
        $densities = array_filter(array_column($clusters, 'density'), static fn ($value) => $value !== null);

        return [
            'cluster_count' => count($clusters),
            'avg_radius_km' => round(array_sum($radii) / count($radii), 4),
            'median_density' => $densities === [] ? 0.0 : round($this->median($densities), 4),
        ];
    }

    protected function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return (float) (($values[$middle - 1] + $values[$middle]) / 2);
    }

    protected function resolveGridSize(float $range, int $points): float
    {
        if ($points <= 1) {
            return max(0.0005, $range);
        }

        $dynamicSize = $range / max(1, sqrt($points));
        return max(0.0005, min(0.05, $dynamicSize));
    }

    private function sanitizeConfidenceThreshold($threshold): float
    {
        if (!is_numeric($threshold)) {
            return 0.0;
        }

        return min(1.0, max(0.0, (float) $threshold));
    }
}
