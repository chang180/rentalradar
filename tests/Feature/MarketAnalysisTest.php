<?php

use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Testing\Fluent\AssertableJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('returns market analysis overview with expected structure', function () {
    $baseDate = CarbonImmutable::parse('2025-01-15');

    collect(range(0, 5))->each(function ($offset) use ($baseDate) {
        Property::factory()->state([
            'district' => 'District Alpha',
            'building_type' => 'Apartment',
            'compartment_pattern' => '2B1B',
            'rent_per_month' => 20000 + ($offset * 500),
            'total_floor_area' => 32 + $offset,
            'total_rent' => 240000 + ($offset * 6000),
            'rent_date' => $baseDate->copy()->subMonths($offset),
        ])->create();

        Property::factory()->state([
            'district' => 'District Beta',
            'building_type' => 'Studio',
            'compartment_pattern' => '1B1B',
            'rent_per_month' => 15000 + ($offset * 300),
            'total_floor_area' => 24 + $offset,
            'total_rent' => 180000 + ($offset * 3600),
            'rent_date' => $baseDate->copy()->subMonths($offset + 1),
        ])->create();
    });

    $response = getJson('/api/analysis/overview');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'trends' => [
                    'timeseries',
                    'summary' => [
                        'current_average',
                        'current_volume',
                        'month_over_month_change',
                        'year_over_year_change',
                        'volume_trend',
                    ],
                    'forecast' => [
                        'method',
                        'values',
                        'confidence',
                    ],
                ],
                'price_comparison' => [
                    'districts',
                    'summary' => [
                        'top_districts',
                        'most_affordable',
                    ],
                    'distribution' => [
                        'segments',
                        'median',
                    ],
                    'filters',
                ],
                'investment' => [
                    'hotspots',
                    'signals' => [
                        'bullish',
                        'bearish',
                        'neutral',
                    ],
                    'confidence',
                ],
                'multi_dimensional' => [
                    'temporal',
                    'spatial',
                    'price_segments',
                ],
                'interactive' => [
                    'trend_series',
                    'price_matrix',
                    'heatmap',
                ],
                'meta' => [
                    'generated_at',
                    'time_range',
                    'filters',
                    'property_count',
                ],
            ],
        ])
        ->assertJsonPath('success', true)
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('data.meta.property_count', fn ($count) => $count >= 1)
            ->whereType('data.trends.timeseries', 'array')
            ->where('data.trends.timeseries.0.period', fn ($value) => is_string($value))
            ->etc());
});

it('generates market analysis report with narrative sections', function () {
    $baseDate = CarbonImmutable::parse('2025-03-01');

    collect(range(0, 3))->each(function ($offset) use ($baseDate) {
        Property::factory()->state([
            'district' => 'District Gamma',
            'building_type' => 'Loft',
            'compartment_pattern' => '3B2B',
            'rent_per_month' => 32000 + ($offset * 800),
            'total_floor_area' => 45 + $offset,
            'total_rent' => 384000 + ($offset * 9600),
            'rent_date' => $baseDate->copy()->subMonths($offset),
        ])->create();
    });

    $response = postJson('/api/analysis/report', [
        'time_range' => '6m',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'report' => [
                'generated_at',
                'time_range',
                'filters',
                'summary',
                'highlights' => [
                    'pricing',
                    'top_market',
                    'hotspot',
                ],
                'recommendations',
                'sections',
            ],
        ])
        ->assertJsonPath('success', true)
        ->assertJsonPath('report.time_range', '6m')
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('report.summary', fn ($summary) => is_string($summary) && $summary !== '')
            ->where('report.sections.0.title', 'Market Overview')
            ->etc());
});

it('applies filters when requesting overview data', function () {
    $baseDate = CarbonImmutable::parse('2025-04-01');

    Property::factory()->count(3)->state([
        'district' => 'Filter District',
        'building_type' => 'Highrise',
        'compartment_pattern' => '2B1B',
        'rent_per_month' => 28000,
        'total_floor_area' => 38,
        'total_rent' => 336000,
        'rent_date' => $baseDate,
    ])->create();

    Property::factory()->count(5)->state([
        'district' => 'Other District',
        'building_type' => 'Studio',
        'compartment_pattern' => '1B0B',
        'rent_per_month' => 18000,
        'total_floor_area' => 28,
        'total_rent' => 216000,
        'rent_date' => $baseDate->subMonth(),
    ])->create();

    $response = getJson('/api/analysis/overview?district=Filter%20District');

    $response->assertOk()
        ->assertJsonPath('data.meta.filters.district', 'Filter District')
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('data.meta.property_count', 3)
            ->where('data.trends.timeseries', fn ($series) => count($series) >= 1)
            ->etc());
});
