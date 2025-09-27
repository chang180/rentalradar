<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Recommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'type',
        'title',
        'summary',
        'reasons',
        'metadata',
        'score',
    ];

    protected $casts = [
        'reasons' => 'array',
        'metadata' => 'array',
        'score' => 'decimal:2',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
