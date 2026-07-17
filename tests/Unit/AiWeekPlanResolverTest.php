<?php

namespace Tests\Unit;

use App\Enums\FoodReferenceType;
use App\Models\CiqualComposition;
use App\Models\CiqualFood;
use App\Models\CiqualNutrient;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Ai\AiWeekPlanResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiWeekPlanResolverTest extends TestCase
{
    use RefreshDatabase;

    private function createCiqualFood(string $name = 'Riz cuit'): CiqualFood
    {
        $food = CiqualFood::create(['alim_code' => 100, 'name_fr' => $name]);
        $kcal = CiqualNutrient::create(['code' => 'ENERGY_KCAL', 'name_fr' => 'Energie', 'unit' => 'kcal']);
        CiqualComposition::create([
            'ciqual_food_id' => $food->id,
            'ciqual_nutrient_id' => $kcal->id,
            'value_per_100g' => 130,
        ]);

        return $food;
    }

    public function test_resolves_recipe_by_id_and_food_by_label(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $recipe = Recipe::create([
            'user_id' => $user->id,
            'name' => 'Bowl protéiné',
            'servings' => 1,
            'is_macro_preset' => true,
            'preset_energy_kcal' => 500,
            'preset_protein_g' => 40,
            'preset_carbs_g' => 40,
            'preset_fat_g' => 15,
        ]);
        $this->createCiqualFood('Riz cuit');

        $parsed = [
            'days' => [[
                'date' => '2026-07-20',
                'slots' => [
                    'breakfast' => [[
                        'label' => 'Bowl',
                        'quantity_g' => null,
                        'recipe_id' => $recipe->id,
                        'recipe_hint' => null,
                    ]],
                    'lunch' => [[
                        'label' => 'Riz cuit',
                        'quantity_g' => 180.0,
                        'recipe_id' => null,
                        'recipe_hint' => null,
                    ]],
                    'dinner' => [],
                    'morning_snack' => [],
                    'afternoon_snack' => [],
                    'night_snack' => [],
                ],
            ]],
        ];

        $draft = app(AiWeekPlanResolver::class)->resolve($user, $parsed);

        $this->assertSame(2, $draft->resolvedCount());
        $this->assertSame(0, $draft->unresolvedCount());
        $this->assertSame('recipe', $draft->items[0]->matchKind);
        $this->assertSame($recipe->id, $draft->items[0]->recipeId);
        $this->assertSame('food', $draft->items[1]->matchKind);
        $this->assertSame(FoodReferenceType::Ciqual->value, $draft->items[1]->referenceType);
        $this->assertSame(180.0, $draft->items[1]->quantityG);
    }

    public function test_marks_unresolved_when_no_match(): void
    {
        Http::fake(['*' => Http::response(['products' => []], 200)]);

        $user = User::factory()->create();
        $parsed = [
            'days' => [[
                'date' => '2026-07-20',
                'slots' => [
                    'breakfast' => [[
                        'label' => 'Plat inventé xyzzy123',
                        'quantity_g' => 100.0,
                        'recipe_id' => null,
                        'recipe_hint' => null,
                    ]],
                    'lunch' => [],
                    'dinner' => [],
                    'morning_snack' => [],
                    'afternoon_snack' => [],
                    'night_snack' => [],
                ],
            ]],
        ];

        $draft = app(AiWeekPlanResolver::class)->resolve($user, $parsed);

        $this->assertSame(0, $draft->resolvedCount());
        $this->assertSame(1, $draft->unresolvedCount());
        $this->assertFalse($draft->items[0]->resolved);
    }
}
