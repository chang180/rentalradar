<?php

namespace App\Services;

use App\Models\CityStatistics;
use App\Models\DistrictStatistics;
use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OptimizedGeoAggregationService
{
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private IntelligentCacheService $cacheService
    ) {}

    public function getAggregatedProperties(array $filters = []): Collection
    {
        // For simple filters, use statistics tables
        if ($this->canUseStatisticsTables($filters)) {
            return $this->getAggregatedFromStatistics($filters);
        }

        // For complex filters, fall back to original query
        return $this->getAggregatedFromProperties($filters);
    }

    public function getCityAggregatedProperties(array $filters = []): Collection
    {
        $query = CityStatistics::query();

        if (isset($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        $results = $query->get();

        return $results->map(function ($item) {
            $center = $this->getCityCenter($item->city);

            return [
                'city' => $item->city,
                'property_count' => $item->total_properties,
                'avg_rent_per_ping' => round($item->avg_rent_per_ping ?? 0, 0),
                'min_rent_per_ping' => round($item->min_rent_per_ping ?? 0, 0),
                'max_rent_per_ping' => round($item->max_rent_per_ping ?? 0, 0),
                'district_count' => $item->district_count,
                'latitude' => $center['lat'] ?? null,
                'longitude' => $center['lng'] ?? null,
                'has_coordinates' => ! is_null($center),
            ];
        });
    }

    public function getStatistics(array $filters = []): array
    {
        if ($this->canUseStatisticsTables($filters)) {
            return $this->getStatisticsFromTables($filters);
        }

        return $this->getStatisticsFromProperties($filters);
    }

    public function getPopularDistricts(int $limit = 10): Collection
    {
        return DistrictStatistics::query()
            ->orderBy('property_count', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return $this->formatDistrictStatistics($item);
            });
    }

    public function getHighestRentDistricts(int $limit = 10): Collection
    {
        return DistrictStatistics::query()
            ->orderBy('avg_rent_per_ping', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return $this->formatDistrictStatistics($item);
            });
    }

    public function getLowestRentDistricts(int $limit = 10): Collection
    {
        return DistrictStatistics::query()
            ->orderBy('avg_rent_per_ping', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return $this->formatDistrictStatistics($item);
            });
    }

    public function getCities(): Collection
    {
        return CityStatistics::query()
            ->orderBy('total_properties', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'city' => $item->city,
                    'property_count' => $item->total_properties,
                    'avg_rent_per_ping' => round($item->avg_rent_per_ping ?? 0, 0),
                ];
            });
    }

    public function getDistrictsByCity(string $city): Collection
    {
        return DistrictStatistics::query()
            ->where('city', $city)
            ->orderBy('property_count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'district' => $item->district,
                    'property_count' => $item->property_count,
                    'avg_rent_per_ping' => round($item->avg_rent_per_ping ?? 0, 0),
                ];
            });
    }

    private function canUseStatisticsTables(array $filters): bool
    {
        // Only use statistics tables for simple filters
        $allowedFilters = ['city', 'district'];

        foreach ($filters as $key => $value) {
            if (! in_array($key, $allowedFilters)) {
                return false;
            }
        }

        return true;
    }

    private function getAggregatedFromStatistics(array $filters = []): Collection
    {
        $query = DistrictStatistics::query();

        if (isset($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        if (isset($filters['district'])) {
            $query->where('district', $filters['district']);
        }

        $results = $query->get();

        return $results->map(function ($item) {
            return $this->formatDistrictStatistics($item);
        });
    }

    private function getAggregatedFromProperties(array $filters = []): Collection
    {
        // Fall back to original implementation for complex filters
        $query = Property::query()
            ->select([
                'city',
                'district',
                DB::raw('COUNT(*) as property_count'),
                DB::raw('AVG(total_rent) as avg_rent'),
                DB::raw('AVG(rent_per_ping) as avg_rent_per_ping'),
                DB::raw('MIN(total_rent) as min_rent'),
                DB::raw('MAX(total_rent) as max_rent'),
                DB::raw('AVG(area_ping) as avg_area_ping'),
                DB::raw('AVG(building_age) as avg_building_age'),
                DB::raw('SUM(CASE WHEN has_elevator = 1 THEN 1 ELSE 0 END) as elevator_count'),
                DB::raw('SUM(CASE WHEN has_management_organization = 1 THEN 1 ELSE 0 END) as management_count'),
                DB::raw('SUM(CASE WHEN has_furniture = 1 THEN 1 ELSE 0 END) as furniture_count'),
            ])
            ->groupBy('city', 'district');

        // Apply filters
        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '') {
                switch ($key) {
                    case 'city':
                        $query->where('city', $value);
                        break;
                    case 'district':
                        $query->where('district', $value);
                        break;
                    case 'building_type':
                        $query->where('building_type', $value);
                        break;
                    case 'rental_type':
                        $query->where('rental_type', $value);
                        break;
                }
            }
        }

        $results = $query->get();

        return $results->map(function ($item) {
            $normalizedCity = $this->normalizeCityNameForGeoService($item->city);
            $center = $this->getCoordinatesDirect($normalizedCity, $item->district);

            return [
                'city' => $item->city,
                'district' => $item->district,
                'property_count' => $item->property_count,
                'avg_rent' => round($item->avg_rent, 0),
                'avg_rent_per_ping' => round($item->avg_rent_per_ping, 0),
                'min_rent' => round($item->min_rent, 0),
                'max_rent' => round($item->max_rent, 0),
                'avg_area_ping' => round($item->avg_area_ping ?? 0, 1),
                'avg_building_age' => round($item->avg_building_age ?? 0, 1),
                'elevator_ratio' => round(($item->elevator_count / $item->property_count) * 100, 1),
                'management_ratio' => round(($item->management_count / $item->property_count) * 100, 1),
                'furniture_ratio' => round(($item->furniture_count / $item->property_count) * 100, 1),
                'latitude' => $center['lat'] ?? null,
                'longitude' => $center['lng'] ?? null,
                'has_coordinates' => ! is_null($center) && isset($center['lat']) && isset($center['lng']),
            ];
        });
    }

    private function getStatisticsFromTables(array $filters = []): array
    {
        if (isset($filters['city']) && isset($filters['district'])) {
            $stats = DistrictStatistics::where('city', $filters['city'])
                ->where('district', $filters['district'])
                ->first();

            if (! $stats) {
                return $this->getEmptyStatistics();
            }

            return [
                'total_properties' => $stats->property_count,
                'total_districts' => 1,
                'total_cities' => 1,
                'avg_rent_per_ping' => $stats->avg_rent_per_ping,
                'min_rent_per_ping' => $stats->avg_rent_per_ping,
                'max_rent_per_ping' => $stats->avg_rent_per_ping,
            ];
        }

        if (isset($filters['city'])) {
            $districts = DistrictStatistics::where('city', $filters['city'])->get();

            return [
                'total_properties' => $districts->sum('property_count'),
                'total_districts' => $districts->count(),
                'total_cities' => 1,
                'avg_rent_per_ping' => $districts->avg('avg_rent_per_ping'),
                'min_rent_per_ping' => $districts->min('avg_rent_per_ping'),
                'max_rent_per_ping' => $districts->max('avg_rent_per_ping'),
            ];
        }

        $districts = DistrictStatistics::all();

        return [
            'total_properties' => $districts->sum('property_count'),
            'total_districts' => $districts->count(),
            'total_cities' => $districts->groupBy('city')->count(),
            'avg_rent_per_ping' => $districts->avg('avg_rent_per_ping'),
            'min_rent_per_ping' => $districts->min('avg_rent_per_ping'),
            'max_rent_per_ping' => $districts->max('avg_rent_per_ping'),
        ];
    }

    private function getStatisticsFromProperties(array $filters = []): array
    {
        // Implement original statistics calculation for complex filters
        $query = Property::query();

        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '') {
                switch ($key) {
                    case 'city':
                        $query->where('city', $value);
                        break;
                    case 'district':
                        $query->where('district', $value);
                        break;
                    case 'building_type':
                        $query->where('building_type', $value);
                        break;
                    case 'rental_type':
                        $query->where('rental_type', $value);
                        break;
                }
            }
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_properties,
            COUNT(DISTINCT CONCAT(city, district)) as total_districts,
            COUNT(DISTINCT city) as total_cities,
            AVG(rent_per_ping) as avg_rent_per_ping,
            MIN(rent_per_ping) as min_rent_per_ping,
            MAX(rent_per_ping) as max_rent_per_ping
        ')->first();

        return [
            'total_properties' => $stats->total_properties ?? 0,
            'total_districts' => $stats->total_districts ?? 0,
            'total_cities' => $stats->total_cities ?? 0,
            'avg_rent_per_ping' => $stats->avg_rent_per_ping,
            'min_rent_per_ping' => $stats->min_rent_per_ping,
            'max_rent_per_ping' => $stats->max_rent_per_ping,
        ];
    }

    private function formatDistrictStatistics($item): array
    {
        $normalizedCity = $this->normalizeCityNameForGeoService($item->city);
        $center = $this->getCoordinatesDirect($normalizedCity, $item->district);

        return [
            'city' => $item->city,
            'district' => $item->district,
            'property_count' => $item->property_count,
            'avg_rent' => round($item->avg_rent ?? 0, 0),
            'avg_rent_per_ping' => round($item->avg_rent_per_ping ?? 0, 0),
            'min_rent' => round($item->min_rent ?? 0, 0),
            'max_rent' => round($item->max_rent ?? 0, 0),
            'avg_area_ping' => round($item->avg_area_ping ?? 0, 1),
            'avg_building_age' => round($item->avg_building_age ?? 0, 1),
            'elevator_ratio' => round(($item->elevator_ratio ?? 0) * 100, 1),
            'management_ratio' => round(($item->management_ratio ?? 0) * 100, 1),
            'furniture_ratio' => round(($item->furniture_ratio ?? 0) * 100, 1),
            'latitude' => $center['lat'] ?? null,
            'longitude' => $center['lng'] ?? null,
            'has_coordinates' => ! is_null($center) && isset($center['lat']) && isset($center['lng']),
        ];
    }

    private function getEmptyStatistics(): array
    {
        return [
            'total_properties' => 0,
            'total_districts' => 0,
            'total_cities' => 0,
            'avg_rent_per_ping' => 0,
            'min_rent_per_ping' => 0,
            'max_rent_per_ping' => 0,
        ];
    }

    private function normalizeCityNameForGeoService(string $city): string
    {
        $mapping = [
            '臺中市' => '台中市',
            '臺南市' => '台南市',
            '臺東縣' => '台東縣',
        ];

        return $mapping[$city] ?? $city;
    }

    private function getCoordinatesDirect(string $city, string $district): ?array
    {
        return TaiwanGeoCenterService::getGeoCenter($city, $district);
    }

    public function getCityCenter(string $city): ?array
    {
        return TaiwanGeoCenterService::getCityCenter($city);
    }

    public function getDistrictBounds(string $district): ?array
    {
        return TaiwanGeoCenterService::getDistrictBounds($district);
    }

    public function getDistrictBoundsByCity(string $city, string $district): ?array
    {
        $center = TaiwanGeoCenterService::getGeoCenter($city, $district);

        if ($center && isset($center['lat']) && isset($center['lng'])) {
            $lat = $center['lat'];
            $lng = $center['lng'];

            // Calculate approximately 5km radius bounds
            $latOffset = 0.045; // ~5km
            $lngOffset = 0.045; // ~5km

            return [
                'north' => $lat + $latOffset,
                'south' => $lat - $latOffset,
                'east' => $lng + $lngOffset,
                'west' => $lng - $lngOffset,
            ];
        }

        // Fallback to general query
        return $this->getDistrictBounds($district);
    }

    public function getBuildingTypeStats(): Collection
    {
        return Property::query()
            ->select([
                'building_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(total_rent) as avg_rent'),
                DB::raw('AVG(rent_per_ping) as avg_rent_per_ping'),
            ])
            ->groupBy('building_type')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->building_type,
                    'count' => $item->count,
                    'avg_rent' => round($item->avg_rent, 0),
                    'avg_rent_per_ping' => round($item->avg_rent_per_ping, 0),
                ];
            });
    }

    public function getRentalTypeStats(): Collection
    {
        return Property::query()
            ->select([
                'rental_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(total_rent) as avg_rent'),
            ])
            ->groupBy('rental_type')
            ->orderBy('count', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'type' => $item->rental_type,
                    'count' => $item->count,
                    'avg_rent' => round($item->avg_rent, 0),
                ];
            });
    }
}
