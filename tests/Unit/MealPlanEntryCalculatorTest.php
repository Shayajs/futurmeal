<?php

namespace Tests\Unit;

use App\Data\NutrientProfile;
use App\Enums\ActivityLevel;
use App\Enums\FoodReferenceType;
use App\Enums\Gender;
use App\Enums\GoalType;
use App\Models\CiqualComposition;
use App\Models\CiqualFood;
use App\Models\CiqualNutrient;
use App\Models\MealPlan;
use App\Models\MealPlanEntry;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Dashboard\DashboardService;
use App\Services\Nutrition\MealPlanEntryCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MealPlanEntryCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private MealPlanEntryCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(MealPlanEntryCalculator::class);
    }

    public function test_calculates_food_line_from_ciqual(): void
    {
        $food = CiqualFood::create([
            'alim_code' => 12345,
            'name_fr' => 'Poulet',
        ]);

        $kcal = CiqualNutrient::create(['code' => 'ENERGY_KCAL', 'name_fr' => 'Energie', 'unit' => 'kcal']);
        CiqualComposition::create([
            'ciqual_food_id' => $food->id,
            'ciqual_nutrient_id' => $kcal->id,
            'value_per_100g' => 200,
        ]);

        $entry = new MealPlanEntry([
            'reference_type' => FoodReferenceType::Ciqual,
            'reference_id' => $food->id,
            'label' => 'Poulet',
            'quantity_g' => 150,
        ]);

        $profile = $this->calculator->calculate($entry);
        $this->assertEquals(300, $profile->energyKcal);
        $this->assertSame('150 g · Poulet', $this->calculator->displayLine($entry));
    }

    public function test_legacy_recipe_entry_still_works(): void
    {
        $user = User::factory()->create();
        $recipe = \App\Models\Recipe::create([
            'user_id' => $user->id,
            'name' => 'Preset',
            'is_macro_preset' => true,
            'preset_energy_kcal' => 400,
            'preset_protein_g' => 30,
            'preset_carbs_g' => 40,
            'preset_fat_g' => 10,
        ]);

        $entry = new MealPlanEntry([
            'recipe_id' => $recipe->id,
            'portions' => 1,
        ]);
        $entry->setRelation('recipe', $recipe);

        $this->assertEquals(400, $this->calculator->calculate($entry)->energyKcal);
    }
}
