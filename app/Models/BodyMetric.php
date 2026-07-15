<?php

namespace App\Models;

use App\Enums\BodyMetricSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BodyMetric extends Model
{
    protected $fillable = [
        'user_id',
        'recorded_at',
        'weight_kg',
        'body_fat_percent',
        'lean_mass_kg',
        'bmi',
        'source',
        'neck_cm',
        'waist_cm',
        'hip_cm',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'date',
            'weight_kg' => 'float',
            'body_fat_percent' => 'float',
            'lean_mass_kg' => 'float',
            'bmi' => 'float',
            'source' => BodyMetricSource::class,
            'neck_cm' => 'float',
            'waist_cm' => 'float',
            'hip_cm' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
