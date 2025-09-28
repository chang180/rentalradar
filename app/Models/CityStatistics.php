<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CityStatistics extends Model
{
    protected $fillable = [
        'city',
        'district_count',
        'total_properties',
        'avg_rent_per_ping',
        'min_rent_per_ping',
        'max_rent_per_ping',
        'last_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'avg_rent_per_ping' => 'decimal:2',
            'min_rent_per_ping' => 'decimal:2',
            'max_rent_per_ping' => 'decimal:2',
            'last_updated_at' => 'datetime',
        ];
    }
}
