<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CiqualComposition extends Model
{
    public $timestamps = false;

    protected $table = 'ciqual_composition';

    protected $fillable = [
        'ciqual_food_id',
        'ciqual_nutrient_id',
        'value_per_100g',
    ];

    protected function casts(): array
    {
        return [
            'value_per_100g' => 'float',
        ];
    }

    public function nutrient(): BelongsTo
    {
        return $this->belongsTo(CiqualNutrient::class, 'ciqual_nutrient_id');
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(CiqualFood::class, 'ciqual_food_id');
    }
}
