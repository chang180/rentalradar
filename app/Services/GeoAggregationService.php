<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GeoAggregationService
{
    /**
     * 取得聚合後的租賃資料（按縣市行政區分組）
     */
    public function getAggregatedProperties(array $filters = []): Collection
    {
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

        // 應用篩選條件
        if (isset($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        if (isset($filters['district'])) {
            $query->where('district', $filters['district']);
        }

        if (isset($filters['building_type'])) {
            $query->where('building_type', $filters['building_type']);
        }

        if (isset($filters['rental_type'])) {
            $query->where('rental_type', $filters['rental_type']);
        }

        if (isset($filters['min_rent'])) {
            $query->having('avg_rent', '>=', $filters['min_rent']);
        }

        if (isset($filters['max_rent'])) {
            $query->having('avg_rent', '<=', $filters['max_rent']);
        }

        if (isset($filters['min_rent_per_ping'])) {
            $query->having('avg_rent_per_ping', '>=', $filters['min_rent_per_ping']);
        }

        if (isset($filters['max_rent_per_ping'])) {
            $query->having('avg_rent_per_ping', '<=', $filters['max_rent_per_ping']);
        }

        $results = $query->get();

        // 為每個聚合結果添加地理中心點
        return $results->map(function ($item) {
            $center = TaiwanGeoCenterService::getCenter($item->city, $item->district);

            return [
                'city' => $item->city,
                'district' => $item->district,
                'property_count' => $item->property_count,
                'avg_rent' => round($item->avg_rent, 0),
                'avg_rent_per_ping' => round($item->avg_rent_per_ping, 0),
                'min_rent' => round($item->min_rent, 0),
                'max_rent' => round($item->max_rent, 0),
                'avg_area_ping' => round($item->avg_area_ping, 1),
                'avg_building_age' => round($item->avg_building_age, 1),
                'elevator_ratio' => round(($item->elevator_count / $item->property_count) * 100, 1),
                'management_ratio' => round(($item->management_count / $item->property_count) * 100, 1),
                'furniture_ratio' => round(($item->furniture_count / $item->property_count) * 100, 1),
                'latitude' => $center['lat'] ?? null,
                'longitude' => $center['lng'] ?? null,
                'has_coordinates' => ! is_null($center),
            ];
        });
    }

    /**
     * 取得縣市層級的聚合資料
     */
    public function getCityAggregatedProperties(array $filters = []): Collection
    {
        $query = Property::query()
            ->select([
                'city',
                DB::raw('COUNT(*) as property_count'),
                DB::raw('AVG(total_rent) as avg_rent'),
                DB::raw('AVG(rent_per_ping) as avg_rent_per_ping'),
                DB::raw('MIN(total_rent) as min_rent'),
                DB::raw('MAX(total_rent) as max_rent'),
                DB::raw('AVG(area_ping) as avg_area_ping'),
                DB::raw('COUNT(DISTINCT district) as district_count'),
            ])
            ->groupBy('city');

        // 應用篩選條件
        if (isset($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        $results = $query->get();

        // 為每個縣市添加地理中心點（使用第一個行政區的中心點）
        return $results->map(function ($item) {
            $districts = TaiwanGeoCenterService::getDistricts($item->city);
            $firstDistrict = $districts[0] ?? null;
            $center = $firstDistrict ? TaiwanGeoCenterService::getCenter($item->city, $firstDistrict) : null;

            return [
                'city' => $item->city,
                'property_count' => $item->property_count,
                'avg_rent' => round($item->avg_rent, 0),
                'avg_rent_per_ping' => round($item->avg_rent_per_ping, 0),
                'min_rent' => round($item->min_rent, 0),
                'max_rent' => round($item->max_rent, 0),
                'avg_area_ping' => round($item->avg_area_ping, 1),
                'district_count' => $item->district_count,
                'latitude' => $center['lat'] ?? null,
                'longitude' => $center['lng'] ?? null,
                'has_coordinates' => ! is_null($center),
            ];
        });
    }

    /**
     * 取得熱門區域統計（按物件數量排序）
     */
    public function getPopularDistricts(int $limit = 10): Collection
    {
        return $this->getAggregatedProperties()
            ->sortByDesc('property_count')
            ->take($limit);
    }

    /**
     * 取得租金最高的區域
     */
    public function getHighestRentDistricts(int $limit = 10): Collection
    {
        return $this->getAggregatedProperties()
            ->sortByDesc('avg_rent_per_ping')
            ->take($limit);
    }

    /**
     * 取得租金最低的區域
     */
    public function getLowestRentDistricts(int $limit = 10): Collection
    {
        return $this->getAggregatedProperties()
            ->sortBy('avg_rent_per_ping')
            ->take($limit);
    }

    /**
     * 取得建築類型統計
     */
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

    /**
     * 取得租賃類型統計
     */
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

    /**
     * 取得縣市列表
     */
    public function getCities(): Collection
    {
        return $this->getAggregatedProperties()
            ->groupBy('city')
            ->map(function ($items, $city) {
                return [
                    'city' => $city,
                    'property_count' => $items->sum('property_count'),
                    'avg_rent_per_ping' => $items->avg('avg_rent_per_ping'),
                ];
            })
            ->sortByDesc('property_count')
            ->values();
    }

    /**
     * 取得指定縣市的行政區列表
     */
    public function getDistrictsByCity(string $city): Collection
    {
        return $this->getAggregatedProperties(['city' => $city])
            ->map(function ($item) {
                return [
                    'district' => $item['district'],
                    'property_count' => $item['property_count'],
                    'avg_rent_per_ping' => $item['avg_rent_per_ping'],
                ];
            })
            ->sortByDesc('property_count')
            ->values();
    }
}
