<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistrictStatistics extends Model
{
    protected $fillable = [
        'city',
        'district',
        'property_count',
        'avg_rent',
        'avg_rent_per_ping',
        'min_rent',
        'max_rent',
        'avg_area_ping',
        'avg_building_age',
        'elevator_ratio',
        'management_ratio',
        'furniture_ratio',
        'last_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'avg_rent' => 'decimal:2',
            'avg_rent_per_ping' => 'decimal:2',
            'min_rent' => 'decimal:2',
            'max_rent' => 'decimal:2',
            'avg_area_ping' => 'decimal:2',
            'avg_building_age' => 'decimal:1',
            'elevator_ratio' => 'decimal:2',
            'management_ratio' => 'decimal:2',
            'furniture_ratio' => 'decimal:2',
            'last_updated_at' => 'datetime',
        ];
    }
}
