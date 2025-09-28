<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AIMapOptimizationService
{
    /**
     * K-means 聚合演算法
     */
    public function clusteringAlgorithm(array $data, string $algorithm = 'kmeans', int $nClusters = 10): array
    {
        try {
            Log::info('Starting clustering algorithm', [
                'algorithm' => $algorithm,
                'n_clusters' => $nClusters,
                'data_points' => count($data),
            ]);

            if (empty($data)) {
                return [
                    'success' => false,
                    'clusters' => [],
                    'algorithm_info' => [
                        'algorithm' => $algorithm,
                        'n_clusters' => $nClusters,
                        'error' => 'No data provided',
                    ],
                ];
            }

            if ($algorithm === 'kmeans') {
                $clusters = $this->kmeansClustering($data, $nClusters);
            } else {
                $clusters = $this->gridClustering($data);
            }

            return [
                'success' => true,
                'clusters' => $clusters,
                'algorithm_info' => [
                    'algorithm' => $algorithm,
                        'n_clusters' => $nClusters,
                    'total_points' => count($data),
                    'cluster_count' => count($clusters),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Clustering algorithm failed', [
                'error' => $e->getMessage(),
                'algorithm' => $algorithm,
            ]);

            return [
                'success' => false,
                'clusters' => [],
                'algorithm_info' => [
                    'algorithm' => $algorithm,
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * 生成熱力圖資料
     */
    public function generateHeatmap(array $data, string $resolution = 'medium'): array
    {
        try {
            Log::info('Generating heatmap', [
                'resolution' => $resolution,
                'data_points' => count($data),
            ]);

            if (empty($data)) {
                return [
                    'success' => false,
                    'heatmap_points' => [],
                    'color_scale' => [],
                    'statistics' => [],
                ];
            }

            $heatmapPoints = [];
            $priceStats = $this->calculatePriceStats($data);

            foreach ($data as $point) {
                $price = $point['price'] ?? 0;
                $weight = $this->calculateWeight($price, $priceStats);

                $heatmapPoints[] = [
                    'lat' => $point['lat'],
                    'lng' => $point['lng'],
                    'weight' => $weight,
                    'price' => $price,
                    'intensity' => $weight,
                ];
            }

            $colorScale = $this->generateColorScale($priceStats);
            $statistics = $this->generateHeatmapStats($heatmapPoints, $priceStats);

            return [
                'success' => true,
                'heatmap_points' => $heatmapPoints,
                'color_scale' => $colorScale,
                'statistics' => $statistics,
            ];
        } catch (\Exception $e) {
            Log::error('Heatmap generation failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'heatmap_points' => [],
                'color_scale' => [],
                'statistics' => [],
            ];
        }
    }

    /**
     * 價格預測
     */
    public function predictPrices(array $properties): array
    {
        try {
            Log::info('Starting price prediction', [
                'properties_count' => count($properties),
            ]);

            $predictions = [];
            $totalConfidence = 0;

            foreach ($properties as $property) {
                $prediction = $this->predictSinglePrice($property);
                $predictions[] = $prediction;
                $totalConfidence += $prediction['confidence'];
            }

            $averageConfidence = count($predictions) > 0 ? $totalConfidence / count($predictions) : 0;

            // 計算價格統計
            $prices = array_column($predictions, 'price');
            $prices = array_filter($prices, function ($price) {
                return $price !== null && $price > 0;
            });

            $averagePrice = count($prices) > 0 ? array_sum($prices) / count($prices) : 0;
            $medianPrice = count($prices) > 0 ? $this->calculateMedian($prices) : 0;
            $minPrice = count($prices) > 0 ? min($prices) : 0;
            $maxPrice = count($prices) > 0 ? max($prices) : 0;

            // 計算價格標準差
            $priceStdDev = 0;
            if (count($prices) > 1) {
                $variance = array_sum(array_map(function ($price) use ($averagePrice) {
                    return pow($price - $averagePrice, 2);
                }, $prices)) / count($prices);
                $priceStdDev = sqrt($variance);
            }

            return [
                'success' => true,
                'predictions' => $predictions,
                'summary' => [
                    'total_predictions' => count($predictions),
                    'average_price' => $averagePrice,
                    'median_price' => $medianPrice,
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                    'price_std_dev' => $priceStdDev,
                    'average_confidence' => $averageConfidence,
                    'confidence_distribution' => $this->calculateConfidenceDistribution($predictions),
                    'confidence_percentiles' => $this->calculateConfidencePercentiles($predictions),
                ],
                'model_info' => [
                    'version' => '1.0.0',
                    'algorithm' => 'simplified_ml',
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Price prediction failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'predictions' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * K-means 聚合實作
     */
    private function kmeansClustering(array $data, int $k): array
    {
        if (count($data) <= $k) {
            // 如果資料點少於群集數，每個點自成一群
            $clusters = [];
            foreach ($data as $index => $point) {
                $clusters[] = [
                    'id' => "cluster_{$index}",
                    'center' => [
                        'lat' => $point['lat'],
                        'lng' => $point['lng'],
                    ],
                    'count' => 1,
                    'bounds' => $this->calculateBounds([$point]),
                    'radius_km' => 0.5,
                    'density' => 1.0,
                    'visual_level' => 1,
                    'price_stats' => [
                        'avg' => $point['price'] ?? 0,
                        'min' => $point['price'] ?? 0,
                        'max' => $point['price'] ?? 0,
                        'median' => $point['price'] ?? 0,
                    ],
                ];
            }

            return $clusters;
        }

        // K-means 實作，匹配 JS 版本邏輯
        $centers = $this->initializeCenters($data, $k);
        $maxIterations = 15; // 匹配 JS 版本
        $shiftTolerance = 0.025; // 匹配 JS 版本 (~25 公尺)
        $clusters = [];
        $assignments = array_fill(0, count($data), -1);

        for ($iter = 0; $iter < $maxIterations; $iter++) {
            $newCenters = array_fill(0, $k, ['lat' => 0, 'lng' => 0]);
            $counts = array_fill(0, $k, 0);
            $errors = array_fill(0, count($data), 0);
            $assignmentChanged = false;

            // 分配點到最近的中心
            foreach ($data as $pointIndex => $point) {
                $minDistance = PHP_FLOAT_MAX;
                $closestCenter = 0;

                foreach ($centers as $i => $center) {
                    $distance = $this->calculateDistance($point, $center);
                    if ($distance < $minDistance) {
                        $minDistance = $distance;
                        $closestCenter = $i;
                    }
                }

                if ($assignments[$pointIndex] !== $closestCenter) {
                    $assignmentChanged = true;
                    $assignments[$pointIndex] = $closestCenter;
                }

                $errors[$pointIndex] = $minDistance;
                $newCenters[$closestCenter]['lat'] += $point['lat'];
                $newCenters[$closestCenter]['lng'] += $point['lng'];
                $counts[$closestCenter]++;
            }

            // 更新中心點並檢查收斂
            $maxShift = 0;
            for ($i = 0; $i < $k; $i++) {
                if ($counts[$i] === 0) {
                    // 處理空聚類：重新分配錯誤最大的點
                    $maxError = -1;
                    $maxErrorIndex = null;
                    foreach ($errors as $idx => $error) {
                        if ($assignments[$idx] !== $i && $error > $maxError) {
                            $maxError = $error;
                            $maxErrorIndex = $idx;
                        }
                    }

                    if ($maxErrorIndex !== null) {
                        $centers[$i] = [
                            'lat' => $data[$maxErrorIndex]['lat'],
                            'lng' => $data[$maxErrorIndex]['lng'],
                        ];
                        $assignments[$maxErrorIndex] = $i;
                        $assignmentChanged = true;
                    }
                    continue;
                }

                $newCenter = [
                    'lat' => $newCenters[$i]['lat'] / $counts[$i],
                    'lng' => $newCenters[$i]['lng'] / $counts[$i],
                ];

                $shift = $this->calculateDistance($centers[$i], $newCenter);
                $centers[$i] = $newCenter;
                $maxShift = max($maxShift, $shift);
            }

            if (!$assignmentChanged && $maxShift <= $shiftTolerance) {
                break;
            }
        }

        // 建立最終聚合
        $memberPoints = array_fill(0, $k, []);
        foreach ($assignments as $pointIndex => $clusterIndex) {
            if ($clusterIndex >= 0) {
                $memberPoints[$clusterIndex][] = $data[$pointIndex];
            }
        }

        foreach ($memberPoints as $i => $points) {
            if (count($points) === 0) continue;

            $prices = array_column($points, 'price');
            $prices = array_filter($prices, function ($price) {
                return $price !== null && $price > 0;
            });

            $radius = $this->calculateClusterRadius($points, $centers[$i]);
            $density = $radius > 0 ? count($points) / (pi() * $radius ** 2) : null;

            $clusters[] = [
                'id' => "cluster_{$i}",
                'center' => [
                    'lat' => round($centers[$i]['lat'], 6),
                    'lng' => round($centers[$i]['lng'], 6),
                ],
                'count' => count($points),
                'bounds' => $this->calculateBounds($points),
                'radius_km' => round($radius, 4),
                'density' => $density !== null ? round($density, 4) : null,
                'visual_level' => $this->calculateVisualLevel(count($points), $density, count($prices) > 0 ? array_sum($prices) / count($prices) : 0),
                'price_stats' => [
                    'avg' => count($prices) > 0 ? round(array_sum($prices) / count($prices)) : 0,
                    'min' => count($prices) > 0 ? round(min($prices)) : 0,
                    'max' => count($prices) > 0 ? round(max($prices)) : 0,
                    'median' => count($prices) > 0 ? round($this->calculateMedian($prices)) : 0,
                ],
            ];
        }

        return $clusters;
    }

    /**
     * 網格聚合實作
     */
    private function gridClustering(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        $gridSize = 0.01; // 約 1km
        $grid = [];

        foreach ($data as $point) {
            $latIndex = floor($point['lat'] / $gridSize);
            $lngIndex = floor($point['lng'] / $gridSize);
            $key = "{$latIndex}_{$lngIndex}";
            
            if (! isset($grid[$key])) {
                $grid[$key] = [];
            }
            $grid[$key][] = $point;
        }

        $clusters = [];
        $clusterId = 0;

        foreach ($grid as $cellPoints) {
            if (count($cellPoints) > 0) {
                $centerLat = array_sum(array_column($cellPoints, 'lat')) / count($cellPoints);
                $centerLng = array_sum(array_column($cellPoints, 'lng')) / count($cellPoints);

                $prices = array_column($cellPoints, 'price');
                $prices = array_filter($prices, function ($price) {
                    return $price !== null && $price > 0;
                });

                $clusters[] = [
                    'id' => "cluster_{$clusterId}",
                    'center' => [
                        'lat' => $centerLat,
                        'lng' => $centerLng,
                    ],
                    'count' => count($cellPoints),
                    'bounds' => $this->calculateBounds($cellPoints),
                    'radius_km' => $gridSize * 50, // 約 0.5km
                    'density' => count($cellPoints) / ($gridSize * 50),
                    'visual_level' => min(5, max(1, intval(count($cellPoints) / 5) + 1)),
                    'price_stats' => [
                        'avg' => count($prices) > 0 ? array_sum($prices) / count($prices) : 0,
                        'min' => count($prices) > 0 ? min($prices) : 0,
                        'max' => count($prices) > 0 ? max($prices) : 0,
                        'median' => count($prices) > 0 ? $this->calculateMedian($prices) : 0,
                    ],
                ];
                $clusterId++;
            }
        }

        return $clusters;
    }

    /**
     * 計算兩點間距離 (km)
     */
    private function calculateDistance(array $point1, array $point2): float
    {
        $lat1 = deg2rad($point1['lat']);
        $lng1 = deg2rad($point1['lng']);
        $lat2 = deg2rad($point2['lat']);
        $lng2 = deg2rad($point2['lng']);

        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;

        $a = sin($dlat / 2) * sin($dlat / 2) +
             cos($lat1) * cos($lat2) *
             sin($dlng / 2) * sin($dlng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = 6371 * $c; // 地球半徑 6371km

        return $distance;
    }

    /**
     * 初始化 K-means 中心點 (決定性方法，匹配 JS 版本)
     */
    private function initializeCenters(array $data, int $k): array
    {
        // 依緯度排序，與 JS 版本保持一致
        $sorted = $data;
        usort($sorted, function($a, $b) {
            $latCompare = $a['lat'] <=> $b['lat'];
            return $latCompare !== 0 ? $latCompare : $a['lng'] <=> $b['lng'];
        });

        $step = max(1, floor(count($sorted) / $k));
        $centers = [];

        for ($i = 0; $i < $k; $i++) {
            $index = min($i * $step, count($sorted) - 1);
            $centers[] = [
                'lat' => $sorted[$index]['lat'],
                'lng' => $sorted[$index]['lng'],
            ];
        }

        return $centers;
    }

    /**
     * 計算群集半徑
     */
    private function calculateClusterRadius(array $points, array $center): float
    {
        if (empty($points)) {
            return 0.5;
        }

        $maxDistance = 0;
        foreach ($points as $point) {
            $distance = $this->calculateDistance($point, $center);
            $maxDistance = max($maxDistance, $distance);
        }

        return max(0.5, $maxDistance);
    }

    private function calculateBounds(array $points): array
    {
        if (empty($points)) {
            return [
                'north' => 0,
                'south' => 0,
                'east' => 0,
                'west' => 0,
            ];
        }

        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');

        return [
            'north' => max($lats),
            'south' => min($lats),
            'east' => max($lngs),
            'west' => min($lngs),
        ];
    }

    private function calculateConfidenceDistribution(array $predictions): array
    {
        $confidences = array_column($predictions, 'confidence');
        $distribution = [
            'high' => 0,    // > 0.8
            'medium' => 0,  // 0.5 - 0.8
            'low' => 0,     // < 0.5
        ];

        foreach ($confidences as $confidence) {
            if ($confidence > 0.8) {
                $distribution['high']++;
            } elseif ($confidence >= 0.5) {
                $distribution['medium']++;
            } else {
                $distribution['low']++;
            }
        }

        return $distribution;
    }

    private function calculateConfidencePercentiles(array $predictions): array
    {
        $confidences = array_column($predictions, 'confidence');
        sort($confidences);

        $count = count($confidences);
        if ($count === 0) {
            return [
                'p50' => 0,
                'p75' => 0,
                'p90' => 0,
            ];
        }

        return [
            'p50' => $confidences[intval($count * 0.5)],
            'p75' => $confidences[intval($count * 0.75)],
            'p90' => $confidences[intval($count * 0.9)],
        ];
    }

    /**
     * 計算中位數
     */
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    /**
     * 計算視覺等級（匹配 JS 版本）
     */
    private function calculateVisualLevel(int $count, ?float $density, float $avgPrice): int
    {
        $level = 1;

        // 基於數量
        if ($count >= 100) $level += 2;
        else if ($count >= 50) $level += 1.5;
        else if ($count >= 20) $level += 1;

        // 基於密度
        if ($density && $density >= 50) $level += 1;
        else if ($density && $density >= 20) $level += 0.5;

        // 基於價格
        if ($avgPrice >= 40000) $level += 1;
        else if ($avgPrice >= 25000) $level += 0.5;

        return min(5, max(1, round($level)));
    }

    /**
     * 計算價格統計
     */
    private function calculatePriceStats(array $data): array
    {
        $prices = array_column($data, 'price');
        $prices = array_filter($prices, function ($price) {
            return $price !== null && $price > 0;
        });

        if (empty($prices)) {
            return [
                'min' => 0,
                'max' => 0,
                'avg' => 0,
                'median' => 0,
            ];
        }

        return [
            'min' => min($prices),
            'max' => max($prices),
            'avg' => array_sum($prices) / count($prices),
            'median' => $this->calculateMedian($prices),
        ];
    }

    /**
     * 計算權重
     */
    private function calculateWeight(float $price, array $priceStats): float
    {
        if ($priceStats['max'] <= $priceStats['min']) {
            return 0.5;
        }

        return ($price - $priceStats['min']) / ($priceStats['max'] - $priceStats['min']);
    }

    /**
     * 生成顏色比例
     */
    private function generateColorScale(array $priceStats): array
    {
        return [
            'min_color' => '#00ff00',
            'max_color' => '#ff0000',
            'price_range' => [
                'min' => $priceStats['min'],
                'max' => $priceStats['max'],
            ],
        ];
    }

    /**
     * 生成熱力圖統計
     */
    private function generateHeatmapStats(array $heatmapPoints, array $priceStats): array
    {
        return [
            'total_points' => count($heatmapPoints),
            'price_range' => $priceStats,
            'weight_distribution' => [
                'min' => min(array_column($heatmapPoints, 'weight')),
                'max' => max(array_column($heatmapPoints, 'weight')),
                'avg' => array_sum(array_column($heatmapPoints, 'weight')) / count($heatmapPoints),
            ],
        ];
    }

    /**
     * 預測單一價格
     */
    private function predictSinglePrice(array $property): array
    {
        // 簡化的價格預測邏輯
        $basePrice = 25000;
        $area = $property['area'] ?? 20;
        $lat = $property['lat'] ?? 25.033;
        $lng = $property['lng'] ?? 121.565;

        // 基於面積的調整
        $areaMultiplier = 1 + ($area - 20) * 0.02;

        // 基於位置的調整 (距離台北市中心的距離)
        $distanceFromCenter = $this->calculateDistance(
            ['lat' => $lat, 'lng' => $lng],
            ['lat' => 25.033, 'lng' => 121.565]
        );
        $locationMultiplier = max(0.5, 1 - $distanceFromCenter * 0.1);

        $predictedPrice = $basePrice * $areaMultiplier * $locationMultiplier;
        $confidence = max(0.5, 1 - $distanceFromCenter * 0.05);

        return [
            'id' => $property['id'] ?? null,
            'index' => $property['index'] ?? null,
            'price' => round($predictedPrice),
            'predicted_price' => round($predictedPrice),
            'confidence' => min(0.95, $confidence),
            'factors' => [
                'area' => $area,
                'area_multiplier' => $areaMultiplier,
                'location_multiplier' => $locationMultiplier,
                'distance_from_center' => $distanceFromCenter,
            ],
        ];
    }
}
