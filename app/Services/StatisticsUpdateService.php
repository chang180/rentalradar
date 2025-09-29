<?php

namespace App\Services;

use App\Models\CityStatistics;
use App\Models\DistrictStatistics;
use App\Models\Property;

class StatisticsUpdateService
{
    public function __construct(
        private IntelligentCacheService $cacheService
    ) {}

    public function updateDistrictStatistics(string $city, string $district): void
    {
        $stats = $this->calculateDistrictStats($city, $district);

        DistrictStatistics::updateOrCreate(
            ['city' => $city, 'district' => $district],
            array_merge($stats, ['last_updated_at' => now()])
        );

        $this->updateCityStatistics($city);

        $this->cacheService->clearDistrictCache($city, $district);
    }

    public function updateCityStatistics(string $city): void
    {
        $districtStats = DistrictStatistics::where('city', $city)->get();

        $stats = [
            'district_count' => $districtStats->count(),
            'total_properties' => $districtStats->sum('property_count'),
            'avg_rent_per_ping' => $districtStats->avg('avg_rent_per_ping'),
            'min_rent_per_ping' => $districtStats->min('avg_rent_per_ping'),
            'max_rent_per_ping' => $districtStats->max('avg_rent_per_ping'),
            'last_updated_at' => now(),
        ];

        CityStatistics::updateOrCreate(
            ['city' => $city],
            $stats
        );

        $this->cacheService->clearCityCache($city);
    }

    private function calculateDistrictStats(string $city, string $district): array
    {
        $query = Property::where('city', $city)
            ->where('district', $district)
            ->whereNotNull('total_rent')
            ->whereNotNull('rent_per_ping');

        $stats = $query->selectRaw('
            COUNT(*) as property_count,
            AVG(total_rent) as avg_rent,
            AVG(rent_per_ping) as avg_rent_per_ping,
            MIN(total_rent) as min_rent,
            MAX(total_rent) as max_rent,
            AVG(area_ping) as avg_area_ping,
            AVG(building_age) as avg_building_age,
            AVG(CASE WHEN has_elevator = 1 THEN 1.0 ELSE 0.0 END) as elevator_ratio,
            AVG(CASE WHEN has_management_organization = 1 THEN 1.0 ELSE 0.0 END) as management_ratio,
            AVG(CASE WHEN has_furniture = 1 THEN 1.0 ELSE 0.0 END) as furniture_ratio
        ')->first();

        return [
            'property_count' => $stats->property_count ?? 0,
            'avg_rent' => $stats->avg_rent,
            'avg_rent_per_ping' => $stats->avg_rent_per_ping,
            'min_rent' => $stats->min_rent,
            'max_rent' => $stats->max_rent,
            'avg_area_ping' => $stats->avg_area_ping,
            'avg_building_age' => $stats->avg_building_age ?? 0,
            'elevator_ratio' => $stats->elevator_ratio,
            'management_ratio' => $stats->management_ratio,
            'furniture_ratio' => $stats->furniture_ratio,
        ];
    }
}
