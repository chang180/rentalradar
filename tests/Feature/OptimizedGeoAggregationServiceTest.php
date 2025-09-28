<?php

use App\Models\CityStatistics;
use App\Models\DistrictStatistics;
use App\Models\Property;
use App\Services\OptimizedGeoAggregationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('can get aggregated properties from statistics tables', function () {
    // Create test data
    Property::factory()->create([
        'city' => '台北市',
        'district' => '中正區',
        'total_rent' => 25000,
        'rent_per_ping' => 1500,
        'area_ping' => 16.67,
        'building_age' => 10,
        'has_elevator' => true,
        'has_management_organization' => true,
        'has_furniture' => false,
    ]);

    Property::factory()->create([
        'city' => '台北市',
        'district' => '大安區',
        'total_rent' => 30000,
        'rent_per_ping' => 1800,
        'area_ping' => 16.67,
        'building_age' => 5,
        'has_elevator' => true,
        'has_management_organization' => true,
        'has_furniture' => true,
    ]);

    // Populate statistics
    Artisan::call('statistics:populate');

    $service = app(OptimizedGeoAggregationService::class);
    $result = $service->getAggregatedProperties();

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->count())->toBeGreaterThan(0);
});

test('can get cities from statistics tables', function () {
    // Create test data and populate statistics
    Property::factory()->create([
        'city' => '台北市',
        'district' => '中正區',
        'total_rent' => 25000,
        'rent_per_ping' => 1500,
    ]);

    Artisan::call('statistics:populate');

    $service = app(OptimizedGeoAggregationService::class);
    $result = $service->getCities();

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->count())->toBeGreaterThan(0);
});

test('can get districts by city from statistics tables', function () {
    // Create test data and populate statistics
    Property::factory()->create([
        'city' => '台北市',
        'district' => '中正區',
        'total_rent' => 25000,
        'rent_per_ping' => 1500,
    ]);

    Property::factory()->create([
        'city' => '台北市',
        'district' => '大安區',
        'total_rent' => 30000,
        'rent_per_ping' => 1800,
    ]);

    Artisan::call('statistics:populate');

    $service = app(OptimizedGeoAggregationService::class);
    $result = $service->getDistrictsByCity('台北市');

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    expect($result->count())->toBeGreaterThan(0);
});

test('can get statistics from tables', function () {
    // Create test data and populate statistics
    Property::factory()->create([
        'city' => '台北市',
        'district' => '中正區',
        'total_rent' => 25000,
        'rent_per_ping' => 1500,
    ]);

    Artisan::call('statistics:populate');

    $service = app(OptimizedGeoAggregationService::class);
    $result = $service->getStatistics();

    expect($result)->toBeArray();
    expect($result)->toHaveKeys(['total_properties', 'total_districts', 'total_cities']);
    expect($result['total_properties'])->toBeGreaterThan(0);
});

test('statistics show correct data consistency', function () {
    // Create test data and populate statistics
    Property::factory()->create([
        'city' => '台北市',
        'district' => '中正區',
        'total_rent' => 25000,
        'rent_per_ping' => 1500,
    ]);

    Property::factory()->create([
        'city' => '台北市',
        'district' => '大安區',
        'total_rent' => 30000,
        'rent_per_ping' => 1800,
    ]);

    Artisan::call('statistics:populate');

    // Check that district statistics sum matches city statistics
    $districtStats = DistrictStatistics::all();
    $cityStats = CityStatistics::all();

    $totalPropertiesFromDistricts = $districtStats->sum('property_count');
    $totalPropertiesFromCities = $cityStats->sum('total_properties');

    expect($totalPropertiesFromDistricts)->toBe($totalPropertiesFromCities);
});

test('optimized service falls back to property queries for complex filters', function () {
    // Create test data
    Property::factory()->create([
        'city' => '台北市',
        'district' => '中正區',
        'building_type' => '住宅',
        'total_rent' => 25000,
        'rent_per_ping' => 1500,
    ]);

    $service = app(OptimizedGeoAggregationService::class);

    // Use a filter that should trigger fallback to Property model
    $result = $service->getAggregatedProperties(['building_type' => '住宅']);

    expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});
