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
        static $code = 100;
        $food = CiqualFood::create(['alim_code' => $code++, 'name_fr' => $name]);
        $kcal = CiqualNutrient::firstOrCreate(
            ['code' => 'ENERGY_KCAL'],
            ['name_fr' => 'Energie', 'unit' => 'kcal'],
        );
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

    public function test_matches_common_ai_labels_via_tokens(): void
    {
        Http::fake(['*' => Http::response(['products' => []], 200)]);

        $user = User::factory()->create();
        $this->createCiqualFood('Poulet, blanc, sans peau, cuit');
        $this->createCiqualFood('Huile d\'olive');
        $this->createCiqualFood('Skyr nature');

        $parsed = [
            'days' => [[
                'date' => '2026-07-20',
                'slots' => [
                    'breakfast' => [[
                        'label' => 'Skyr',
                        'quantity_g' => 300.0,
                        'recipe_id' => null,
                        'recipe_hint' => null,
                    ]],
                    'lunch' => [[
                        'label' => 'Blanc de poulet',
                        'quantity_g' => 200.0,
                        'recipe_id' => null,
                        'recipe_hint' => null,
                    ]],
                    'dinner' => [[
                        'label' => 'Huile d\'olive',
                        'quantity_g' => 10.0,
                        'recipe_id' => null,
                        'recipe_hint' => null,
                    ]],
                    'morning_snack' => [],
                    'afternoon_snack' => [],
                    'night_snack' => [],
                ],
            ]],
        ];

        $draft = app(AiWeekPlanResolver::class)->resolve($user, $parsed);

        $this->assertSame(3, $draft->resolvedCount());
        $this->assertSame(0, $draft->unresolvedCount());
    }

    public function test_rejects_weak_off_false_positives(): void
    {
        Http::fake([
            '*' => Http::response([
                'products' => [[
                    'code' => '123',
                    'product_name' => 'Fromage Blanc Nature',
                    'brands' => 'X',
                    'nutriments' => [],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();

        $parsed = [
            'days' => [[
                'date' => '2026-07-20',
                'slots' => [
                    'breakfast' => [[
                        'label' => 'Pomme',
                        'quantity_g' => 150.0,
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
    }

    public function test_matches_recipe_via_hint_fuzzy(): void
    {
        Http::fake();

        $user = User::factory()->create();
        $recipe = Recipe::create([
            'user_id' => $user->id,
            'name' => 'Porridge express',
            'servings' => 1,
            'is_macro_preset' => true,
            'preset_energy_kcal' => 350,
            'preset_protein_g' => 25,
            'preset_carbs_g' => 40,
            'preset_fat_g' => 8,
        ]);

        $parsed = [
            'days' => [[
                'date' => '2026-07-20',
                'slots' => [
                    'breakfast' => [[
                        'label' => 'Flocons d\'avoine',
                        'quantity_g' => 50.0,
                        'recipe_id' => null,
                        'recipe_hint' => 'Porridge express',
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

        $this->assertSame(1, $draft->resolvedCount());
        $this->assertSame('recipe', $draft->items[0]->matchKind);
        $this->assertSame($recipe->id, $draft->items[0]->recipeId);
    }

    public function test_matches_whey_and_oeufs_via_aliases(): void
    {
        Http::fake(['*' => Http::response(['products' => []], 200)]);

        $user = User::factory()->create();
        $this->createCiqualFood('Protéines de lactosérum (whey) en poudre');
        $this->createCiqualFood('Œuf, cru');

        $parsed = [
            'days' => [[
                'date' => '2026-07-20',
                'slots' => [
                    'breakfast' => [[
                        'label' => 'Whey isolat vanille',
                        'quantity_g' => 30.0,
                        'recipe_id' => null,
                        'recipe_hint' => 'Shaker whey isolat',
                    ]],
                    'lunch' => [[
                        'label' => 'Œufs entiers',
                        'quantity_g' => 100.0,
                        'recipe_id' => null,
                        'recipe_hint' => 'Œufs brouillés',
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
        $this->assertSame('food', $draft->items[0]->matchKind);
        $this->assertSame('food', $draft->items[1]->matchKind);
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

    public function test_uses_ai_macros_when_no_catalogue_match(): void
    {
        Http::fake(['*' => Http::response(['products' => []], 200)]);

        $user = User::factory()->create();
        $parsed = [
            'days' => [[
                'date' => '2026-07-20',
                'slots' => [
                    'breakfast' => [[
                        'label' => 'Plat inventé xyzzy999',
                        'quantity_g' => 200.0,
                        'recipe_id' => null,
                        'recipe_hint' => null,
                        'protein_g' => 40.0,
                        'carbs_g' => 20.0,
                        'fat_g' => 10.0,
                        'energy_kcal' => 330.0,
                        'price_eur' => 2.5,
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

        $this->assertSame(1, $draft->resolvedCount());
        $this->assertSame(0, $draft->unresolvedCount());
        $this->assertSame('ai_estimate', $draft->items[0]->matchKind);
        $this->assertSame(40.0, $draft->items[0]->proteinG);
        $this->assertSame(2.5, $draft->items[0]->priceEur);
    }
}
