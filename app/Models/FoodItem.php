<?php

namespace App\Models;

use App\Enums\FoodReferenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodItem extends Model
{
    protected $fillable = [
        'user_id',
        'reference_type',
        'external_id',
        'name',
        'brand',
        'energy_kcal',
        'protein_g',
        'carbs_g',
        'fat_g',
        'fiber_g',
        'salt_g',
        'raw_nutriments',
        'is_community',
    ];

    protected function casts(): array
    {
        return [
            'reference_type' => FoodReferenceType::class,
            'energy_kcal' => 'float',
            'protein_g' => 'float',
            'carbs_g' => 'float',
            'fat_g' => 'float',
            'fiber_g' => 'float',
            'salt_g' => 'float',
            'raw_nutriments' => 'array',
            'is_community' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
