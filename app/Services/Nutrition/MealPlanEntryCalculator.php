<?php

namespace App\Services\Nutrition;

use App\Data\NutrientProfile;
use App\Enums\FoodReferenceType;
use App\Models\FoodItem;
use App\Models\MealPlanEntry;
use App\Models\RecipeIngredient;

class MealPlanEntryCalculator
{
    public function __construct(
        private RecipeCalculator $recipeCalculator,
        private NutritionResolver $resolver,
    ) {}

    public function calculate(MealPlanEntry $entry): NutrientProfile
    {
        $entry->loadMissing(['recipe.ingredients', 'foodItem']);

        if ($this->isFoodLine($entry)) {
            return $this->calculateFoodLine($entry);
        }

        if ($entry->recipe_id && $entry->recipe) {
            return $this->recipeCalculator->calculate($entry->recipe, (float) ($entry->portions ?? 1));
        }

        return new NutrientProfile;
    }

    public function label(MealPlanEntry $entry): string
    {
        if ($entry->label) {
            return $entry->label;
        }

        return $entry->recipe?->name ?? 'Aliment';
    }

    public function displayLine(MealPlanEntry $entry): string
    {
        $label = $this->label($entry);

        if ($this->isFoodLine($entry)) {
            return round((float) $entry->quantity_g).' g · '.$label;
        }

        if ($entry->recipe_id) {
            $portions = (float) ($entry->portions ?? 1);
            if ($portions !== 1.0) {
                return $label.' (×'.$portions.')';
            }
        }

        return $label;
    }

    private function isFoodLine(MealPlanEntry $entry): bool
    {
        return $entry->quantity_g !== null
            && ($entry->reference_type || $entry->food_item_id);
    }

    private function calculateFoodLine(MealPlanEntry $entry): NutrientProfile
    {
        $ingredient = new RecipeIngredient([
            'reference_type' => $entry->reference_type,
            'reference_id' => $entry->reference_id,
            'food_item_id' => $entry->food_item_id,
            'label' => $entry->label ?? '',
            'quantity_g' => (float) $entry->quantity_g,
        ]);

        if ($entry->food_item_id && $entry->foodItem) {
            $ingredient->setRelation('foodItem', $entry->foodItem);
        }

        $per100 = $this->resolver->profileForIngredient($ingredient);

        return $per100->scale((float) $entry->quantity_g);
    }
}
