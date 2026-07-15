<?php

namespace Tests\Unit;

use App\Data\NutrientProfile;
use App\Services\Nutrition\RecipeCalculator;
use App\Models\Recipe;
use PHPUnit\Framework\TestCase;

class NutrientProfileTest extends TestCase
{
    public function test_scale_and_add(): void
    {
        $base = new NutrientProfile(energyKcal: 100, proteinG: 10, carbsG: 20, fatG: 5);
        $scaled = $base->scale(200);
        $this->assertEquals(200.0, $scaled->energyKcal);
        $this->assertEquals(20.0, $scaled->proteinG);

        $total = $base->add($scaled);
        $this->assertEquals(300.0, $total->energyKcal);
    }

    public function test_macro_preset_recipe(): void
    {
        $recipe = new Recipe([
            'is_macro_preset' => true,
            'preset_energy_kcal' => 500,
            'preset_protein_g' => 40,
            'preset_carbs_g' => 50,
            'preset_fat_g' => 15,
            'servings' => 2,
        ]);

        $calculator = new RecipeCalculator(new \App\Services\Nutrition\NutritionResolver);
        $result = $calculator->calculate($recipe, 1);

        $this->assertEquals(250.0, $result->energyKcal);
        $this->assertEquals(20.0, $result->proteinG);
    }
}
