<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiskAssessment extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'risk_level',
        'risk_score',
        'factors',
        'suggestions',
        'metadata',
    ];

    protected $casts = [
        'risk_score' => 'decimal:2',
        'factors' => 'array',
        'suggestions' => 'array',
        'metadata' => 'array',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
