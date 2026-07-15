<?php

namespace App\Models;

use App\Data\NutrientProfile;
use App\Services\Nutrition\RecipeCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'instructions',
        'servings',
        'is_macro_preset',
        'preset_energy_kcal',
        'preset_protein_g',
        'preset_carbs_g',
        'preset_fat_g',
        'preset_portion_g',
        'themealdb_id',
    ];

    protected function casts(): array
    {
        return [
            'is_macro_preset' => 'boolean',
            'servings' => 'integer',
            'preset_energy_kcal' => 'float',
            'preset_protein_g' => 'float',
            'preset_carbs_g' => 'float',
            'preset_fat_g' => 'float',
            'preset_portion_g' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('sort_order');
    }

    public function nutrientProfile(?float $portions = 1): NutrientProfile
    {
        return app(RecipeCalculator::class)->calculate($this, $portions);
    }
}
