<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anomaly extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'category',
        'severity',
        'description',
        'context',
        'resolution',
    ];

    protected $casts = [
        'context' => 'array',
        'resolution' => 'array',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
