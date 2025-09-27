<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    /** @use HasFactory<\Database\Factories\PropertyFactory> */
    use HasFactory;

    protected $fillable = [
        'district',
        'village',
        'road',
        'land_section',
        'land_subsection',
        'land_number',
        'building_type',
        'total_floor_area',
        'main_use',
        'main_building_materials',
        'construction_completion_year',
        'total_floors',
        'compartment_pattern',
        'has_management_organization',
        'rent_per_month',
        'total_rent',
        'rent_date',
        'rental_period',
        'latitude',
        'longitude',
        'full_address',
        'is_geocoded',
        'data_source',
        'is_processed',
        'processing_notes',
    ];

    protected function casts(): array
    {
        return [
            'rent_date' => 'date',
            'has_management_organization' => 'boolean',
            'is_geocoded' => 'boolean',
            'is_processed' => 'boolean',
            'processing_notes' => 'array',
            'total_floor_area' => 'decimal:2',
            'rent_per_month' => 'decimal:2',
            'total_rent' => 'decimal:2',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function getFormattedAddressAttribute(): string
    {
        $parts = array_filter([
            $this->district,
            $this->village,
            $this->road,
        ]);

        return implode('', $parts);
    }

    public function scopeGeocoded($query)
    {
        return $query->where('is_geocoded', true);
    }

    public function scopeWithinBounds($query, float $northLat, float $southLat, float $eastLng, float $westLng)
    {
        return $query->whereBetween('latitude', [$southLat, $northLat])
                    ->whereBetween('longitude', [$westLng, $eastLng]);
    }

    public function scopeByDistrict($query, string $district)
    {
        return $query->where('district', $district);
    }

    public function scopeRentDateBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('rent_date', [$startDate, $endDate]);
    }
}
