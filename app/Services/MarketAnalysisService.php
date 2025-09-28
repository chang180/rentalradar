<?php

namespace App\Services;

use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MarketAnalysisService
{
    private const PING_IN_SQUARE_METERS = 3.305785;

    public function getDashboardData(array $filters = []): array
    {
        try {
            $timeRange = $filters['time_range'] ?? '12m';
            
            // 生成快取鍵值，包含所有篩選條件
            $cacheKey = $this->generateCacheKey('market_analysis_dashboard', $filters);
            
            // 嘗試從 Redis 快取取得資料（快取 10 天，因為資料每 10 天更新一次）
            $cachedData = Cache::store('redis')->get($cacheKey);
            if ($cachedData !== null) {
                Log::info('Market analysis data served from Redis cache', ['cache_key' => $cacheKey]);
                return $cachedData;
            }
            
            $startDate = $this->resolveStartDate($timeRange);

            // 先檢查是否有資料，避免無謂的查詢
            $hasData = Property::query()
                ->when($startDate, fn ($query) => $query->whereDate('rent_date', '>=', $startDate))
                ->when(isset($filters['district']), fn ($query) => $query->where('district', $filters['district']))
                ->when(isset($filters['building_type']), fn ($query) => $query->where('building_type', $filters['building_type']))
                ->whereNotNull('total_rent')
                ->whereNotNull('rent_date')
                ->exists();

            if (!$hasData) {
                $emptyData = $this->buildEmptyDashboard($timeRange, $filters);
                // 快取空資料到 Redis 1 小時，避免重複查詢
                Cache::store('redis')->put($cacheKey, $emptyData, now()->addHour());
                return $emptyData;
            }

            $properties = Property::query()
                ->select([
                    'id',
                    'city',
                    'district',
                    'building_type',
                    'rental_type',
                    'area_ping',
                    'bedrooms',
                    'living_rooms',
                    'bathrooms',
                    'building_age',
                    'has_elevator',
                    'has_management_organization',
                    'has_furniture',
                    'total_rent',
                    'rent_per_ping',
                    'rent_date',
                ])
                ->when($startDate, fn ($query) => $query->whereDate('rent_date', '>=', $startDate))
                ->when(isset($filters['district']), fn ($query) => $query->where('district', $filters['district']))
                ->when(isset($filters['building_type']), fn ($query) => $query->where('building_type', $filters['building_type']))
                ->whereNotNull('total_rent')
                ->whereNotNull('rent_date')
                ->orderBy('rent_date', 'desc')
                ->limit(3000) // 進一步減少資料量
                ->get();

            if ($properties->isEmpty()) {
                return $this->buildEmptyDashboard($timeRange, $filters);
            }

            $trends = $this->buildTrendAnalysis($properties);
            $priceComparison = $this->buildPriceComparison($properties, $trends['timeseries']);
            $investment = $this->buildInvestmentInsights($trends['timeseries'], $priceComparison['districts']);
            $multiDimensional = $this->buildMultiDimensionalAnalysis($properties, $priceComparison['districts']);
            $interactive = $this->buildInteractiveDatasets($trends, $priceComparison, $multiDimensional);

            $result = [
                'trends' => $trends,
                'price_comparison' => $priceComparison,
                'investment' => $investment,
                'multi_dimensional' => $multiDimensional,
                'interactive' => $interactive,
                'meta' => [
                    'generated_at' => now()->toAtomString(),
                    'time_range' => $timeRange,
                    'filters' => $filters,
                    'property_count' => $properties->count(),
                ],
            ];
            
            // 快取結果到 Redis 10 天（因為資料每 10 天更新一次）
            Cache::store('redis')->put($cacheKey, $result, now()->addDays(10));
            Log::info('Market analysis data cached', ['cache_key' => $cacheKey, 'property_count' => $properties->count()]);
            
            return $result;
        } catch (\Exception $e) {
            Log::error('MarketAnalysisService::getDashboardData failed: ' . $e->getMessage(), [
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);
            
            // 回傳空資料而不是拋出錯誤
            return $this->buildEmptyDashboard($filters['time_range'] ?? '12m', $filters);
        }
    }

    public function generateReport(array $filters = []): array
    {
        // 生成報告快取鍵值
        $cacheKey = $this->generateCacheKey('market_analysis_report', $filters);
        
        // 嘗試從 Redis 快取取得報告
        $cachedReport = Cache::store('redis')->get($cacheKey);
        if ($cachedReport !== null) {
            Log::info('Market analysis report served from Redis cache', ['cache_key' => $cacheKey]);
            return $cachedReport;
        }
        
        $dashboard = $this->getDashboardData($filters);
        $trends = $dashboard['trends'];
        $priceComparison = $dashboard['price_comparison'];
        $investment = $dashboard['investment'];

        $latestTrend = collect($trends['timeseries'])->last();
        $topDistrict = $priceComparison['summary']['top_districts'][0] ?? null;
        $emerging = $investment['hotspots'][0] ?? null;

        $summary = $this->buildReportSummary($latestTrend, $topDistrict, $emerging);

        $report = [
            'generated_at' => now()->toAtomString(),
            'time_range' => $dashboard['meta']['time_range'],
            'filters' => $dashboard['meta']['filters'],
            'summary' => $summary,
            'highlights' => [
                'pricing' => $latestTrend
                    ? sprintf(
                        '本期平均租金為 %s，中位數 %s，共計 %s 筆物件。',
                        $this->formatCurrency($latestTrend['average_rent']),
                        $this->formatCurrency($latestTrend['median_rent']),
                        $latestTrend['volume']
                    )
                    : '資料不足，暫無價格重點可供參考。',
                'top_market' => $topDistrict
                    ? sprintf(
                        '%s 以平均租金 %s 與 %d 筆有效物件領先市場。',
                        $topDistrict['district'],
                        $this->formatCurrency($topDistrict['average_rent']),
                        $topDistrict['listings']
                    )
                    : '尚未找到表現最佳的行政區。',
                'hotspot' => $emerging
                    ? sprintf(
                        '%s 綜合評分 %.2f，趨勢判定為 %s，具備成長潛力。',
                        $emerging['district'],
                        $emerging['score'],
                        $emerging['trend_direction']
                    )
                    : '尚未偵測到投資熱點。',
            ],
            'recommendations' => $this->buildRecommendations($investment, $priceComparison, $trends),
            'sections' => [
                [
                    'title' => '市場總覽',
                    'content' => $summary,
                    'metrics' => [
                        'average_rent' => $latestTrend['average_rent'] ?? null,
                        'median_rent' => $latestTrend['median_rent'] ?? null,
                        'month_over_month' => $trends['summary']['month_over_month_change'] ?? null,
                        'year_over_year' => $trends['summary']['year_over_year_change'] ?? null,
                    ],
                ],
                [
                    'title' => '區域表現',
                    'content' => $this->buildRegionalNarrative($priceComparison),
                    'metrics' => [
                        'top_districts' => $priceComparison['summary']['top_districts'],
                        'most_affordable' => $priceComparison['summary']['most_affordable'],
                        'distribution' => $priceComparison['distribution'],
                    ],
                ],
                [
                    'title' => '投資展望',
                    'content' => $this->buildInvestmentNarrative($investment),
                    'metrics' => [
                        'hotspots' => $investment['hotspots'],
                        'signals' => $investment['signals'],
                        'forecast' => $trends['forecast'],
                    ],
                ],
            ],
        ];
        
        // 快取報告 10 天
        Cache::store('redis')->put($cacheKey, $report, now()->addDays(10));
        Log::info('Market analysis report cached', ['cache_key' => $cacheKey]);
        
        return $report;
    }

    private function buildEmptyDashboard(string $timeRange, array $filters): array
    {
        return [
            'trends' => [
                'timeseries' => [],
                'summary' => [
                    'current_average' => null,
                    'current_volume' => null,
                    'month_over_month_change' => null,
                    'year_over_year_change' => null,
                    'volume_trend' => null,
                ],
                'forecast' => [
                    'method' => 'linear_projection',
                    'values' => [],
                    'confidence' => 0.0,
                ],
            ],
            'price_comparison' => [
                'districts' => [],
                'summary' => [
                    'top_districts' => [],
                    'most_affordable' => [],
                ],
                'distribution' => [
                    'segments' => [],
                    'median' => null,
                ],
                'filters' => $this->defaultFilters(),
            ],
            'investment' => [
                'hotspots' => [],
                'signals' => [
                    'bullish' => [],
                    'bearish' => [],
                    'neutral' => [],
                ],
                'confidence' => 0.0,
            ],
            'multi_dimensional' => [
                'temporal' => [],
                'spatial' => [],
                'price_segments' => [],
            ],
            'interactive' => [
                'trend_series' => [],
                'price_matrix' => [],
                'heatmap' => [],
            ],
            'meta' => [
                'generated_at' => now()->toAtomString(),
                'time_range' => $timeRange,
                'filters' => $filters,
                'property_count' => 0,
            ],
        ];
    }

    private function buildTrendAnalysis(Collection $properties): array
    {
        $grouped = $properties->groupBy(function ($property) {
            $date = CarbonImmutable::parse($property->rent_date);

            return $date->format('Y-m');
        })->sortKeys();

        $series = [];
        foreach ($grouped as $period => $items) {
            $rentValues = $items->pluck('total_rent')->map(fn ($value) => (float) $value)->sort()->values();
            $areaValues = $items->pluck('area_ping')->map(function ($value) {
                $numeric = $value !== null ? (float) $value : null;

                return $numeric && $numeric > 0 ? $numeric : null;
            })->filter();

            $averageRent = $rentValues->avg() ?? 0.0;
            $medianRent = $this->median($rentValues);
            $averageArea = $areaValues->avg();
            $pricePerPing = $this->pricePerPing($averageRent, $averageArea);

            $series[] = [
                'period' => $period,
                'average_rent' => round($averageRent, 2),
                'median_rent' => round($medianRent, 2),
                'volume' => $items->count(),
                'average_area' => $averageArea ? round($averageArea, 2) : null,
                'price_per_ping' => $pricePerPing,
            ];
        }

        $series = $this->addMovingAverage($series);

        return [
            'timeseries' => $series,
            'summary' => $this->buildTrendSummary($series),
            'forecast' => $this->buildForecast($series),
        ];
    }

    private function addMovingAverage(array $series, int $window = 3): array
    {
        $count = count($series);
        if ($count === 0) {
            return $series;
        }

        for ($index = 0; $index < $count; $index++) {
            $start = max(0, $index - $window + 1);
            $slice = array_slice($series, $start, $index - $start + 1);
            $movingAverage = collect($slice)->avg('average_rent');
            $series[$index]['moving_average'] = $movingAverage ? round($movingAverage, 2) : null;
        }

        return $series;
    }

    private function buildTrendSummary(array $series): array
    {
        $latest = collect($series)->last();
        $previous = collect($series)->reverse()->skip(1)->first();
        $yearAgoIndex = max(count($series) - 13, 0);
        $yearAgo = $series[$yearAgoIndex] ?? null;

        $summary = [
            'current_average' => $latest['average_rent'] ?? null,
            'current_volume' => $latest['volume'] ?? null,
            'month_over_month_change' => null,
            'year_over_year_change' => null,
            'volume_trend' => null,
        ];

        if ($latest && $previous && $previous['average_rent'] > 0) {
            $summary['month_over_month_change'] = round((($latest['average_rent'] - $previous['average_rent']) / $previous['average_rent']) * 100, 2);
        }

        if ($latest && $yearAgo && ($yearAgo['average_rent'] ?? 0) > 0) {
            $summary['year_over_year_change'] = round((($latest['average_rent'] - $yearAgo['average_rent']) / $yearAgo['average_rent']) * 100, 2);
        }

        if ($latest && $previous && $previous['volume'] > 0) {
            $summary['volume_trend'] = round((($latest['volume'] - $previous['volume']) / max($previous['volume'], 1)) * 100, 2);
        }

        return $summary;
    }

    private function buildForecast(array $series): array
    {
        $count = count($series);
        if ($count < 3) {
            return [
                'method' => 'linear_projection',
                'values' => [],
                'confidence' => 0.45,
            ];
        }

        $recent = array_slice($series, max(0, $count - 6));
        $values = array_values(array_map(fn ($item) => $item['average_rent'], $recent));
        $slope = $this->calculateSlope($values);
        $lastValue = end($values) ?: 0.0;

        $forecastValues = [];
        for ($i = 1; $i <= 3; $i++) {
            $forecastValues[] = round($lastValue + ($slope * $i), 2);
        }

        return [
            'method' => 'linear_projection',
            'values' => $forecastValues,
            'confidence' => 0.68,
        ];
    }

    private function buildPriceComparison(Collection $properties, array $trendSeries): array
    {
        $districts = $properties->groupBy('district')->map(function ($group, $district) use ($trendSeries) {
            $rentValues = $group->pluck('total_rent')->map(fn ($value) => (float) $value)->sort()->values();
            $areaValues = $group->pluck('area_ping')->map(function ($value) {
                $numeric = $value !== null ? (float) $value : null;

                return $numeric && $numeric > 0 ? $numeric : null;
            })->filter();

            $averageRent = $rentValues->avg() ?? 0.0;
            $medianRent = $this->median($rentValues);
            $pricePerPing = $this->pricePerPing($averageRent, $areaValues->avg());
            $districtTrend = $this->extractDistrictTrend($group, $trendSeries);

            return [
                'district' => $district,
                'average_rent' => round($averageRent, 2),
                'median_rent' => round($medianRent, 2),
                'price_range' => [
                    'min' => $rentValues->isEmpty() ? null : round($rentValues->first(), 2),
                    'max' => $rentValues->isEmpty() ? null : round($rentValues->last(), 2),
                    'p25' => $rentValues->isEmpty() ? null : round($this->percentile($rentValues->all(), 0.25), 2),
                    'p75' => $rentValues->isEmpty() ? null : round($this->percentile($rentValues->all(), 0.75), 2),
                ],
                'average_area' => $areaValues->isEmpty() ? null : round($areaValues->avg(), 2),
                'price_per_ping' => $pricePerPing,
                'listings' => $group->count(),
                'trend_change' => $districtTrend['change'],
                'trend_direction' => $districtTrend['direction'],
            ];
        })->values()->all();

        $topDistricts = collect($districts)->sortByDesc(fn ($district) => $district['trend_change'] ?? 0)->take(5)->values()->all();
        $mostAffordable = collect($districts)->sortBy('average_rent')->take(5)->values()->all();

        return [
            'districts' => $districts,
            'summary' => [
                'top_districts' => $topDistricts,
                'most_affordable' => $mostAffordable,
            ],
            'distribution' => $this->buildPriceDistribution($properties),
            'filters' => $this->defaultFilters(),
        ];
    }

    private function buildInvestmentInsights(array $trendSeries, array $districts): array
    {
        if ($trendSeries === [] || $districts === []) {
            return [
                'hotspots' => [],
                'signals' => [
                    'bullish' => [],
                    'bearish' => [],
                    'neutral' => [],
                ],
                'confidence' => 0.0,
            ];
        }

        $latestAverage = collect($trendSeries)->last()['average_rent'] ?? null;
        $maxListings = max(array_column($districts, 'listings')) ?: 1;
        $maxTrendChange = max(array_map(fn ($district) => abs($district['trend_change'] ?? 0), $districts)) ?: 1;

        $hotspots = [];
        foreach ($districts as $district) {
            $trendChange = $district['trend_change'] ?? 0.0;
            $direction = $district['trend_direction'] ?? 'neutral';

            $growthScore = $trendChange / $maxTrendChange;
            $volumeScore = $district['listings'] / $maxListings;
            $affordabilityScore = $latestAverage && $district['average_rent'] > 0
                ? max(0.0, 1 - ($district['average_rent'] / $latestAverage))
                : 0.5;

            $score = round(($growthScore * 0.45) + ($volumeScore * 0.3) + ($affordabilityScore * 0.25), 3);

            $hotspots[] = [
                'district' => $district['district'],
                'score' => $score,
                'trend_direction' => $direction,
                'average_rent' => $district['average_rent'],
                'price_per_ping' => $district['price_per_ping'],
                'listings' => $district['listings'],
            ];
        }

        $hotspots = collect($hotspots)->sortByDesc('score')->values()->take(5)->all();

        return [
            'hotspots' => $hotspots,
            'signals' => [
                'bullish' => collect($districts)->where('trend_direction', 'up')->pluck('district')->values()->all(),
                'bearish' => collect($districts)->where('trend_direction', 'down')->pluck('district')->values()->all(),
                'neutral' => collect($districts)->where('trend_direction', 'neutral')->pluck('district')->values()->all(),
            ],
            'confidence' => 0.72,
        ];
    }

    private function buildMultiDimensionalAnalysis(Collection $properties, array $districts): array
    {
        $temporal = $properties->groupBy(function ($property) {
            return CarbonImmutable::parse($property->rent_date)->format('Y-m');
        })->map(function ($group, $period) {
            $values = $group->pluck('total_rent')->map(fn ($value) => (float) $value);

            return [
                'period' => $period,
                'average_rent' => round($values->avg() ?? 0.0, 2),
                'median_rent' => round($this->median($values->sort()->values()), 2),
                'volume' => $group->count(),
            ];
        })->values()->all();

        $spatial = collect($districts)->map(function ($district) use ($properties) {
            $count = $properties->where('district', $district['district'])->count();

            return [
                'district' => $district['district'],
                'listings' => $count,
                'average_rent' => $district['average_rent'],
                'median_rent' => $district['median_rent'],
                'price_per_ping' => $district['price_per_ping'],
            ];
        })->values()->all();

        return [
            'temporal' => $temporal,
            'spatial' => $spatial,
            'price_segments' => $this->buildPriceSegments($properties),
        ];
    }

    private function buildInteractiveDatasets(array $trends, array $priceComparison, array $multiDimensional): array
    {
        return [
            'trend_series' => $trends['timeseries'],
            'price_matrix' => $priceComparison['districts'],
            'heatmap' => $multiDimensional['spatial'],
        ];
    }

    private function buildReportSummary(?array $latestTrend, ?array $topDistrict, ?array $hotspot): string
    {
        if (! $latestTrend) {
            return '目前市場活動量不足，請補充租賃資料以產出完整摘要。';
        }

        $parts = [];
        $parts[] = sprintf(
            '平均月租金為 %s，中位數 %s，共 %d 筆紀錄。',
            $this->formatCurrency($latestTrend['average_rent']),
            $this->formatCurrency($latestTrend['median_rent']),
            $latestTrend['volume']
        );

        if ($topDistrict) {
            $parts[] = sprintf(
                '%s 以平均租金 %s 與 %d 筆物件保持領先。',
                $topDistrict['district'],
                $this->formatCurrency($topDistrict['average_rent']),
                $topDistrict['listings']
            );
        }

        if ($hotspot) {
            $parts[] = sprintf(
                '%s 被標記為投資熱區，綜合評分 %.2f，趨勢動能為 %s。',
                $hotspot['district'],
                $hotspot['score'],
                $hotspot['trend_direction']
            );
        }

        return implode(' ', $parts);
    }

    private function buildRecommendations(array $investment, array $priceComparison, array $trends): array
    {
        $recommendations = [];

        if (! empty($investment['hotspots'])) {
            $recommendations[] = sprintf(
                '建議優先拜訪 %s，以掌握新興需求。',
                $investment['hotspots'][0]['district']
            );
        }

        if (! empty($priceComparison['summary']['most_affordable'])) {
            $districts = collect($priceComparison['summary']['most_affordable'])->pluck('district')->implode(', ');
            $recommendations[] = sprintf('可在 %s 推廣高 CP 值物件以吸引價格敏感客群。', $districts);
        }

        if (($trends['summary']['month_over_month_change'] ?? 0) > 5) {
            $recommendations[] = '近期租金加速上升，建議檢視定價策略以避免過度拉升。';
        }

        if (($trends['summary']['volume_trend'] ?? 0) < -5) {
            $recommendations[] = '物件量出現放緩，建議加強行銷曝光以維持流量。';
        }

        return $recommendations;
    }

    private function buildRegionalNarrative(array $priceComparison): string
    {
        if ($priceComparison['districts'] === []) {
            return '區域比較需更多租賃資料才能完成。';
        }

        $top = $priceComparison['summary']['top_districts'][0] ?? null;
        $affordable = $priceComparison['summary']['most_affordable'][0] ?? null;

        $parts = [];
        if ($top) {
            $parts[] = sprintf(
                '%s 表現優於同儕，平均租金 %s，物件數 %d。',
                $top['district'],
                $this->formatCurrency($top['average_rent']),
                $top['listings']
            );
        }

        if ($affordable) {
            $parts[] = sprintf(
                '%s 仍是最具價格優勢的區域，月租 %s，提供 %d 筆物件。',
                $affordable['district'],
                $this->formatCurrency($affordable['average_rent']),
                $affordable['listings']
            );
        }

        return implode(' ', $parts);
    }

    private function buildInvestmentNarrative(array $investment): string
    {
        if (empty($investment['hotspots'])) {
            return '此期間尚未發現明顯投資熱區，建議持續觀察。';
        }

        $top = $investment['hotspots'][0];

        return sprintf(
            '%s 呈現 %s 走勢，綜合評分 %.2f，擁有 %d 筆物件，顯示良好上升動能。',
            $top['district'],
            $top['trend_direction'],
            $top['score'],
            $top['listings']
        );
    }

    private function resolveStartDate(string $timeRange): ?string
    {
        if (preg_match('/^(\d+)([dwmy])$/', strtolower($timeRange), $matches) !== 1) {
            return null;
        }

        $value = (int) $matches[1];
        $unit = $matches[2];

        return match ($unit) {
            'd' => now()->subDays($value)->toDateString(),
            'w' => now()->subWeeks($value)->toDateString(),
            'm' => now()->subMonths($value)->toDateString(),
            'y' => now()->subYears($value)->toDateString(),
            default => null,
        };
    }

    private function extractDistrictTrend(Collection $districtProperties, array $trendSeries): array
    {
        $grouped = $districtProperties->groupBy(function ($property) {
            return CarbonImmutable::parse($property->rent_date)->format('Y-m');
        })->sortKeys();

        if ($grouped->isEmpty()) {
            return ['change' => null, 'direction' => 'neutral'];
        }

        $latestPeriod = $grouped->keys()->last();
        $previousPeriod = $grouped->keys()->reverse()->skip(1)->first();

        $latestAverage = $grouped[$latestPeriod]->pluck('total_rent')->avg();
        $previousAverage = $previousPeriod ? $grouped[$previousPeriod]->pluck('total_rent')->avg() : null;

        $change = null;
        $direction = 'neutral';

        if ($latestAverage && $previousAverage && $previousAverage > 0) {
            $change = round((($latestAverage - $previousAverage) / $previousAverage) * 100, 2);
            if ($change > 2) {
                $direction = 'up';
            } elseif ($change < -2) {
                $direction = 'down';
            }
        }

        return [
            'change' => $change,
            'direction' => $direction,
        ];
    }

    /**
     * 生成快取鍵值
     */
    private function generateCacheKey(string $prefix, array $filters): string
    {
        // 排序篩選條件以確保鍵值一致性
        ksort($filters);
        
        // 將篩選條件轉換為字串
        $filterString = http_build_query($filters);
        
        // 加入資料庫最後更新時間作為快取版本號
        $lastUpdate = Property::query()
            ->whereNotNull('rent_date')
            ->max('updated_at');
        
        return sprintf(
            '%s:%s:%s',
            $prefix,
            md5($filterString),
            $lastUpdate ? (is_string($lastUpdate) ? strtotime($lastUpdate) : $lastUpdate->timestamp) : '0'
        );
    }

    private function buildPriceDistribution(Collection $properties): array
    {
        $values = $properties->pluck('total_rent')->map(fn ($value) => (float) $value)->sort()->values();
        $median = $this->median($values);

        $segments = [
            ['label' => '10,000 以下', 'min' => 0, 'max' => 10000, 'count' => 0],
            ['label' => '10,000 - 20,000', 'min' => 10000, 'max' => 20000, 'count' => 0],
            ['label' => '20,000 - 35,000', 'min' => 20000, 'max' => 35000, 'count' => 0],
            ['label' => '35,000 - 50,000', 'min' => 35000, 'max' => 50000, 'count' => 0],
            ['label' => '50,000 以上', 'min' => 50000, 'max' => null, 'count' => 0],
        ];

        foreach ($values as $value) {
            foreach ($segments as $index => $segment) {
                if (($segment['max'] === null && $value >= $segment['min']) || ($value >= $segment['min'] && $value < $segment['max'])) {
                    $segments[$index]['count']++;
                    break;
                }
            }
        }

        return [
            'segments' => $segments,
            'median' => $median ? round($median, 2) : null,
        ];
    }

    private function buildPriceSegments(Collection $properties): array
    {
        $segments = [];

        $segments['by_room_type'] = $properties->groupBy(function ($property) {
            return $property->bedrooms.'房'.$property->living_rooms.'廳'.$property->bathrooms.'衛';
        })->map(function ($group, $pattern) {
            $average = $group->pluck('total_rent')->avg();

            return [
                'pattern' => $pattern,
                'average_rent' => $average ? round($average, 2) : null,
                'listings' => $group->count(),
            ];
        })->values()->all();

        $segments['by_building_type'] = $properties->groupBy('building_type')->map(function ($group, $type) {
            $average = $group->pluck('total_rent')->avg();

            return [
                'building_type' => $type,
                'average_rent' => $average ? round($average, 2) : null,
                'listings' => $group->count(),
            ];
        })->values()->all();

        $segments['price_distribution'] = $this->buildPriceDistribution($properties);

        return $segments;
    }

    private function calculateSlope(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        $xSum = 0.0;
        $ySum = 0.0;
        $xySum = 0.0;
        $xSquaredSum = 0.0;

        for ($i = 0; $i < $count; $i++) {
            $x = $i + 1;
            $y = $values[$i];
            $xSum += $x;
            $ySum += $y;
            $xySum += $x * $y;
            $xSquaredSum += $x * $x;
        }

        $denominator = ($count * $xSquaredSum) - ($xSum * $xSum);
        if ($denominator === 0.0) {
            return 0.0;
        }

        return (($count * $xySum) - ($xSum * $ySum)) / $denominator;
    }

    private function median(Collection $values): float
    {
        $count = $values->count();
        if ($count === 0) {
            return 0.0;
        }

        $middle = intdiv($count, 2);

        if ($count % 2) {
            return (float) $values[$middle];
        }

        return (float) (($values[$middle - 1] + $values[$middle]) / 2);
    }

    private function percentile(array $values, float $percent): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        sort($values);

        $rank = $percent * ($count - 1);
        $lowerIndex = (int) floor($rank);
        $upperIndex = (int) ceil($rank);

        if ($lowerIndex === $upperIndex) {
            return (float) $values[$lowerIndex];
        }

        $weight = $rank - $lowerIndex;

        return (float) (($values[$lowerIndex] * (1 - $weight)) + ($values[$upperIndex] * $weight));
    }

    private function pricePerPing(?float $rent, ?float $area): ?float
    {
        if (! $rent || ! $area || $area <= 0) {
            return null;
        }

        $ping = $area / self::PING_IN_SQUARE_METERS;
        if ($ping <= 0) {
            return null;
        }

        return round($rent / $ping, 2);
    }

    private function defaultFilters(): array
    {
        return [
            'dimensions' => [
                'temporal' => ['daily', 'weekly', 'monthly', 'quarterly'],
                'spatial' => ['district', 'village', 'neighborhood'],
                'categorical' => ['room_type', 'building_type', 'floor'],
            ],
            'comparison_modes' => ['side_by_side', 'overlay', 'difference'],
        ];
    }

    private function formatCurrency(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return 'NT$'.number_format($value, 0);
    }
}
