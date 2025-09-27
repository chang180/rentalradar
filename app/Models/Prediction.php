<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'model_version',
        'predicted_price',
        'confidence',
        'range_min',
        'range_max',
        'breakdown',
        'explanations',
        'metadata',
    ];

    protected $casts = [
        'predicted_price' => 'decimal:2',
        'confidence' => 'decimal:4',
        'range_min' => 'decimal:2',
        'range_max' => 'decimal:2',
        'breakdown' => 'array',
        'explanations' => 'array',
        'metadata' => 'array',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
