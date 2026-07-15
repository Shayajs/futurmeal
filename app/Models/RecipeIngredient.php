<?php

namespace App\Models;

use App\Enums\FoodReferenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'recipe_id',
        'reference_type',
        'reference_id',
        'food_item_id',
        'label',
        'quantity_g',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'reference_type' => FoodReferenceType::class,
            'quantity_g' => 'float',
            'sort_order' => 'integer',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function foodItem(): BelongsTo
    {
        return $this->belongsTo(FoodItem::class);
    }
}
