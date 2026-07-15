<?php

namespace App\Services\Nutrition;

use App\Data\NutrientProfile;
use App\Models\Recipe;

class RecipeCalculator
{
    public function __construct(private NutritionResolver $resolver) {}

    public function calculate(Recipe $recipe, float $portions = 1): NutrientProfile
    {
        if ($recipe->is_macro_preset) {
            $factor = $portions / max($recipe->servings, 1);

            return new NutrientProfile(
                energyKcal: round((float) $recipe->preset_energy_kcal * $factor, 2),
                proteinG: round((float) $recipe->preset_protein_g * $factor, 2),
                carbsG: round((float) $recipe->preset_carbs_g * $factor, 2),
                fatG: round((float) $recipe->preset_fat_g * $factor, 2),
            );
        }

        $recipe->loadMissing('ingredients');

        $total = new NutrientProfile;

        foreach ($recipe->ingredients as $ingredient) {
            $per100 = $this->resolver->profileForIngredient($ingredient);
            $total = $total->add($per100->scale((float) $ingredient->quantity_g));
        }

        $factor = $portions / max($recipe->servings, 1);

        return new NutrientProfile(
            energyKcal: round($total->energyKcal * $factor, 2),
            proteinG: round($total->proteinG * $factor, 2),
            carbsG: round($total->carbsG * $factor, 2),
            fatG: round($total->fatG * $factor, 2),
            fiberG: round($total->fiberG * $factor, 2),
            saltG: round($total->saltG * $factor, 2),
        );
    }
}
