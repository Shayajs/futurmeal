<?php

namespace App\Models;

use App\Enums\FoodReferenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MealPlanEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'meal_plan_id',
        'planned_on',
        'meal_slot',
        'recipe_id',
        'reference_type',
        'reference_id',
        'food_item_id',
        'label',
        'quantity_g',
        'portions',
        'estimated_cost',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'planned_on' => 'date',
            'reference_type' => FoodReferenceType::class,
            'quantity_g' => 'float',
            'portions' => 'float',
            'estimated_cost' => 'float',
            'sort_order' => 'integer',
        ];
    }

    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
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
