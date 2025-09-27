<?php

namespace App\Services;

use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MarketAnalysisService
{
    private const PING_IN_SQUARE_METERS = 3.305785;

    public function getDashboardData(array $filters = []): array
    {
        $timeRange = $filters['time_range'] ?? '12m';
        $startDate = $this->resolveStartDate($timeRange);

        $properties = Property::query()
            ->select([
                'id',
                'district',
                'village',
                'building_type',
                'compartment_pattern',
                'total_floor_area',
                'rent_per_month',
                'total_rent',
                'rent_date',
            ])
            ->when($startDate, fn ($query) => $query->whereDate('rent_date', '>=', $startDate))
            ->when(isset($filters['district']), fn ($query) => $query->where('district', $filters['district']))
            ->when(isset($filters['building_type']), fn ($query) => $query->where('building_type', $filters['building_type']))
            ->whereNotNull('rent_per_month')
            ->whereNotNull('rent_date')
            ->get();

        if ($properties->isEmpty()) {
            return $this->buildEmptyDashboard($timeRange, $filters);
        }

        $trends = $this->buildTrendAnalysis($properties);
        $priceComparison = $this->buildPriceComparison($properties, $trends['timeseries']);
        $investment = $this->buildInvestmentInsights($trends['timeseries'], $priceComparison['districts']);
        $multiDimensional = $this->buildMultiDimensionalAnalysis($properties, $priceComparison['districts']);
        $interactive = $this->buildInteractiveDatasets($trends, $priceComparison, $multiDimensional);

        return [
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
    }

    public function generateReport(array $filters = []): array
    {
        $dashboard = $this->getDashboardData($filters);
        $trends = $dashboard['trends'];
        $priceComparison = $dashboard['price_comparison'];
        $investment = $dashboard['investment'];

        $latestTrend = collect($trends['timeseries'])->last();
        $topDistrict = $priceComparison['summary']['top_districts'][0] ?? null;
        $emerging = $investment['hotspots'][0] ?? null;

        $summary = $this->buildReportSummary($latestTrend, $topDistrict, $emerging);

        return [
            'generated_at' => now()->toAtomString(),
            'time_range' => $dashboard['meta']['time_range'],
            'filters' => $dashboard['meta']['filters'],
            'summary' => $summary,
            'highlights' => [
                'pricing' => $latestTrend
                    ? sprintf(
                        'Average rent is %s with median %s and %s listings this period.',
                        $this->formatCurrency($latestTrend['average_rent']),
                        $this->formatCurrency($latestTrend['median_rent']),
                        $latestTrend['volume']
                    )
                    : 'Insufficient data for pricing highlights.',
                'top_market' => $topDistrict
                    ? sprintf(
                        '%s leads the market with average rent %s and %d active listings.',
                        $topDistrict['district'],
                        $this->formatCurrency($topDistrict['average_rent']),
                        $topDistrict['listings']
                    )
                    : 'No top performing district identified.',
                'hotspot' => $emerging
                    ? sprintf(
                        '%s shows strong potential with composite score %.2f and %s trend direction.',
                        $emerging['district'],
                        $emerging['score'],
                        $emerging['trend_direction']
                    )
                    : 'No investment hotspot identified.',
            ],
            'recommendations' => $this->buildRecommendations($investment, $priceComparison, $trends),
            'sections' => [
                [
                    'title' => 'Market Overview',
                    'content' => $summary,
                    'metrics' => [
                        'average_rent' => $latestTrend['average_rent'] ?? null,
                        'median_rent' => $latestTrend['median_rent'] ?? null,
                        'month_over_month' => $trends['summary']['month_over_month_change'] ?? null,
                        'year_over_year' => $trends['summary']['year_over_year_change'] ?? null,
                    ],
                ],
                [
                    'title' => 'Regional Performance',
                    'content' => $this->buildRegionalNarrative($priceComparison),
                    'metrics' => [
                        'top_districts' => $priceComparison['summary']['top_districts'],
                        'most_affordable' => $priceComparison['summary']['most_affordable'],
                        'distribution' => $priceComparison['distribution'],
                    ],
                ],
                [
                    'title' => 'Investment Outlook',
                    'content' => $this->buildInvestmentNarrative($investment),
                    'metrics' => [
                        'hotspots' => $investment['hotspots'],
                        'signals' => $investment['signals'],
                        'forecast' => $trends['forecast'],
                    ],
                ],
            ],
        ];
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
            $rentValues = $items->pluck('rent_per_month')->map(fn ($value) => (float) $value)->sort()->values();
            $areaValues = $items->pluck('total_floor_area')->map(function ($value) {
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
            $rentValues = $group->pluck('rent_per_month')->map(fn ($value) => (float) $value)->sort()->values();
            $areaValues = $group->pluck('total_floor_area')->map(function ($value) {
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
            $values = $group->pluck('rent_per_month')->map(fn ($value) => (float) $value);
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
            return 'Market activity is limited. Additional rental data is required to produce a detailed summary.';
        }

        $parts = [];
        $parts[] = sprintf(
            'Average monthly rent is %s with median %s across %d recorded listings.',
            $this->formatCurrency($latestTrend['average_rent']),
            $this->formatCurrency($latestTrend['median_rent']),
            $latestTrend['volume']
        );

        if ($topDistrict) {
            $parts[] = sprintf(
                '%s currently leads the market with average rent %s and %d active listings.',
                $topDistrict['district'],
                $this->formatCurrency($topDistrict['average_rent']),
                $topDistrict['listings']
            );
        }

        if ($hotspot) {
            $parts[] = sprintf(
                '%s is flagged as an investment hotspot with composite score %.2f and %s trend momentum.',
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
                'Prioritize exploratory visits in %s to capture emerging demand.',
                $investment['hotspots'][0]['district']
            );
        }

        if (! empty($priceComparison['summary']['most_affordable'])) {
            $districts = collect($priceComparison['summary']['most_affordable'])->pluck('district')->implode(', ');
            $recommendations[] = sprintf('Promote value listings across %s to attract price sensitive renters.', $districts);
        }

        if (($trends['summary']['month_over_month_change'] ?? 0) > 5) {
            $recommendations[] = 'Review pricing strategy to avoid over-extension as rents are climbing faster than usual.';
        }

        if (($trends['summary']['volume_trend'] ?? 0) < -5) {
            $recommendations[] = 'Strengthen marketing campaigns to counteract the recent slowdown in listing volume.';
        }

        return $recommendations;
    }

    private function buildRegionalNarrative(array $priceComparison): string
    {
        if ($priceComparison['districts'] === []) {
            return 'Regional comparison requires additional rental records.';
        }

        $top = $priceComparison['summary']['top_districts'][0] ?? null;
        $affordable = $priceComparison['summary']['most_affordable'][0] ?? null;

        $parts = [];
        if ($top) {
            $parts[] = sprintf(
                '%s outperforms peers with average rent %s and %d active listings.',
                $top['district'],
                $this->formatCurrency($top['average_rent']),
                $top['listings']
            );
        }

        if ($affordable) {
            $parts[] = sprintf(
                '%s remains the most affordable district at %s per month, supporting %d listings.',
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
            return 'No clear investment hotspots detected during this period. Maintain watchlist monitoring.';
        }

        $top = $investment['hotspots'][0];

        return sprintf(
            '%s is trending %s with a composite score of %.2f, indicating strong upside potential with %d active listings.',
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

        $latestAverage = $grouped[$latestPeriod]->pluck('rent_per_month')->avg();
        $previousAverage = $previousPeriod ? $grouped[$previousPeriod]->pluck('rent_per_month')->avg() : null;

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

    private function buildPriceDistribution(Collection $properties): array
    {
        $values = $properties->pluck('rent_per_month')->map(fn ($value) => (float) $value)->sort()->values();
        $median = $this->median($values);

        $segments = [
            ['label' => 'Under 10k', 'min' => 0, 'max' => 10000, 'count' => 0],
            ['label' => '10k - 20k', 'min' => 10000, 'max' => 20000, 'count' => 0],
            ['label' => '20k - 35k', 'min' => 20000, 'max' => 35000, 'count' => 0],
            ['label' => '35k - 50k', 'min' => 35000, 'max' => 50000, 'count' => 0],
            ['label' => '50k+', 'min' => 50000, 'max' => null, 'count' => 0],
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

        $segments['by_room_type'] = $properties->groupBy('compartment_pattern')->map(function ($group, $pattern) {
            $average = $group->pluck('rent_per_month')->avg();
            return [
                'pattern' => $pattern,
                'average_rent' => $average ? round($average, 2) : null,
                'listings' => $group->count(),
            ];
        })->values()->all();

        $segments['by_building_type'] = $properties->groupBy('building_type')->map(function ($group, $type) {
            $average = $group->pluck('rent_per_month')->avg();
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
            return 'N/A';
        }

        return '$' . number_format($value, 0);
    }
}

