<?php

namespace Tests\Feature;

use App\Enums\FoodReferenceType;
use App\Enums\GoalType;
use App\Livewire\AiWeekGenerator;
use App\Livewire\MealPlanner;
use App\Livewire\Settings\AiSettings;
use App\Models\CiqualComposition;
use App\Models\CiqualFood;
use App\Models\CiqualNutrient;
use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Nutrition\MealPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiWeekGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedUser(): User
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        UserProfile::create([
            'user_id' => $user->id,
            'goal_type' => GoalType::WeightLoss->value,
            'planning_horizon_days' => 2,
            'daily_calorie_target' => 2000,
            'calorie_adjustment' => -400,
        ]);

        return $user;
    }

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

    public function test_paste_flow_applies_resolved_entries_to_period(): void
    {
        Http::fake();

        $user = $this->onboardedUser();
        $plan = app(MealPlannerService::class)->ensureDefaultPlan($user);
        $food = $this->createCiqualFood('Riz cuit');
        $recipe = Recipe::create([
            'user_id' => $user->id,
            'name' => 'Omelette',
            'servings' => 1,
            'is_macro_preset' => true,
            'preset_energy_kcal' => 300,
            'preset_protein_g' => 25,
            'preset_carbs_g' => 5,
            'preset_fat_g' => 20,
        ]);

        $weekStart = now()->startOfWeek()->toDateString();
        $day2 = now()->startOfWeek()->addDay()->toDateString();

        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => $weekStart,
            'meal_slot' => 'lunch',
            'label' => 'Ancien repas',
            'quantity_g' => 100,
            'reference_type' => FoodReferenceType::Ciqual,
            'reference_id' => $food->id,
        ]);

        $json = json_encode([
            'days' => [
                [
                    'date' => $weekStart,
                    'slots' => [
                        'breakfast' => [['label' => 'Omelette', 'recipe_id' => $recipe->id, 'quantity_g' => null]],
                        'lunch' => [['label' => 'Riz cuit', 'quantity_g' => 180]],
                        'dinner' => [],
                        'morning_snack' => [],
                        'afternoon_snack' => [],
                        'night_snack' => [],
                    ],
                ],
                [
                    'date' => $day2,
                    'slots' => [
                        'breakfast' => [],
                        'lunch' => [['label' => 'Riz cuit', 'quantity_g' => 200]],
                        'dinner' => [],
                        'morning_snack' => [],
                        'afternoon_snack' => [],
                        'night_snack' => [],
                    ],
                ],
            ],
        ]);

        Livewire::actingAs($user)
            ->test(AiWeekGenerator::class, [
                'mealPlanId' => $plan->id,
                'weekStart' => $weekStart,
                'horizonDays' => 2,
                'canEdit' => true,
            ])
            ->call('open')
            ->assertSet('show', true)
            ->assertSet('rangeStart', $weekStart)
            ->assertSet('rangeEnd', $day2)
            ->set('rawResponse', $json)
            ->call('parsePastedResponse')
            ->assertSet('step', 'preview')
            ->call('apply');

        $this->assertDatabaseMissing('meal_plan_entries', [
            'meal_plan_id' => $plan->id,
            'label' => 'Ancien repas',
        ]);

        $this->assertTrue(
            MealPlanEntry::query()
                ->where('meal_plan_id', $plan->id)
                ->whereDate('planned_on', $weekStart)
                ->where('meal_slot', 'breakfast')
                ->where('recipe_id', $recipe->id)
                ->exists()
        );

        $this->assertTrue(
            MealPlanEntry::query()
                ->where('meal_plan_id', $plan->id)
                ->whereDate('planned_on', $weekStart)
                ->where('meal_slot', 'lunch')
                ->where('label', 'Riz cuit')
                ->where('quantity_g', 180)
                ->exists()
        );

        $this->assertTrue(
            MealPlanEntry::query()
                ->where('meal_plan_id', $plan->id)
                ->whereDate('planned_on', $day2)
                ->where('meal_slot', 'lunch')
                ->where('quantity_g', 200)
                ->exists()
        );
    }

    public function test_generate_via_api_with_mocked_http(): void
    {
        $user = $this->onboardedUser();
        $user->update([
            'ai_api_key' => 'sk-test',
            'ai_api_base_url' => 'https://api.example.test/v1',
            'ai_api_model' => 'gpt-test',
        ]);

        $plan = app(MealPlannerService::class)->ensureDefaultPlan($user);
        $this->createCiqualFood('Riz cuit');

        $weekStart = now()->startOfWeek()->toDateString();
        $day2 = now()->startOfWeek()->addDay()->toDateString();

        $payload = [
            'days' => [
                [
                    'date' => $weekStart,
                    'slots' => [
                        'breakfast' => [],
                        'lunch' => [['label' => 'Riz cuit', 'quantity_g' => 150]],
                        'dinner' => [],
                        'morning_snack' => [],
                        'afternoon_snack' => [],
                        'night_snack' => [],
                    ],
                ],
                [
                    'date' => $day2,
                    'slots' => [
                        'breakfast' => [],
                        'lunch' => [['label' => 'Riz cuit', 'quantity_g' => 160]],
                        'dinner' => [],
                        'morning_snack' => [],
                        'afternoon_snack' => [],
                        'night_snack' => [],
                    ],
                ],
            ],
        ];

        Http::fake([
            'api.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => json_encode($payload)]],
                ],
            ], 200),
            '*' => Http::response(['products' => []], 200),
        ]);

        Livewire::actingAs($user)
            ->test(AiWeekGenerator::class, [
                'mealPlanId' => $plan->id,
                'weekStart' => $weekStart,
                'horizonDays' => 2,
                'canEdit' => true,
            ])
            ->call('open')
            ->call('goToPrompt')
            ->call('generateViaApi')
            ->assertSet('step', 'preview')
            ->call('apply');

        $this->assertSame(2, MealPlanEntry::where('meal_plan_id', $plan->id)->count());
    }

    public function test_custom_date_range_is_used_for_apply(): void
    {
        Http::fake();

        $user = $this->onboardedUser();
        $plan = app(MealPlannerService::class)->ensureDefaultPlan($user);
        $this->createCiqualFood('Riz cuit');

        $plannerStart = now()->startOfWeek()->toDateString();
        $customStart = now()->startOfWeek()->addDays(3)->toDateString();
        $customEnd = now()->startOfWeek()->addDays(4)->toDateString();

        $json = json_encode([
            'days' => [
                [
                    'date' => $customStart,
                    'slots' => [
                        'breakfast' => [],
                        'lunch' => [['label' => 'Riz cuit', 'quantity_g' => 110]],
                        'dinner' => [],
                        'morning_snack' => [],
                        'afternoon_snack' => [],
                        'night_snack' => [],
                    ],
                ],
                [
                    'date' => $customEnd,
                    'slots' => [
                        'breakfast' => [],
                        'lunch' => [['label' => 'Riz cuit', 'quantity_g' => 120]],
                        'dinner' => [],
                        'morning_snack' => [],
                        'afternoon_snack' => [],
                        'night_snack' => [],
                    ],
                ],
            ],
        ]);

        Livewire::actingAs($user)
            ->test(AiWeekGenerator::class, [
                'mealPlanId' => $plan->id,
                'weekStart' => $plannerStart,
                'horizonDays' => 7,
                'canEdit' => true,
            ])
            ->call('open')
            ->set('rangeStart', $customStart)
            ->set('rangeEnd', $customEnd)
            ->call('goToPrompt')
            ->assertSet('step', 'prompt')
            ->set('rawResponse', $json)
            ->call('parsePastedResponse')
            ->assertSet('step', 'preview')
            ->call('apply');

        $this->assertTrue(
            MealPlanEntry::query()
                ->where('meal_plan_id', $plan->id)
                ->whereDate('planned_on', $customStart)
                ->where('quantity_g', 110)
                ->exists()
        );
        $this->assertTrue(
            MealPlanEntry::query()
                ->where('meal_plan_id', $plan->id)
                ->whereDate('planned_on', $customEnd)
                ->where('quantity_g', 120)
                ->exists()
        );
        $this->assertSame(2, MealPlanEntry::where('meal_plan_id', $plan->id)->count());
    }

    public function test_rejects_inverted_date_range(): void
    {
        $user = $this->onboardedUser();
        $plan = app(MealPlannerService::class)->ensureDefaultPlan($user);
        $start = now()->startOfWeek()->toDateString();

        Livewire::actingAs($user)
            ->test(AiWeekGenerator::class, [
                'mealPlanId' => $plan->id,
                'weekStart' => $start,
                'horizonDays' => 7,
                'canEdit' => true,
            ])
            ->call('open')
            ->set('rangeStart', $start)
            ->set('rangeEnd', now()->startOfWeek()->subDay()->toDateString())
            ->call('goToPrompt')
            ->assertSet('step', 'consignes')
            ->assertSet('errorMessage', 'La date de fin doit être ≥ à la date de début.');
    }

    public function test_ai_settings_page_saves_key(): void
    {
        $user = $this->onboardedUser();

        Livewire::actingAs($user)
            ->test(AiSettings::class)
            ->set('ai_api_key', 'sk-live-test')
            ->set('ai_api_base_url', 'https://api.openai.com/v1')
            ->set('ai_api_model', 'gpt-4o-mini')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertTrue($user->hasAiApiConfigured());
        $this->assertSame('sk-live-test', $user->ai_api_key);
    }

    public function test_planner_page_loads_with_ai_button_context(): void
    {
        $user = $this->onboardedUser();

        Livewire::actingAs($user)
            ->test(MealPlanner::class)
            ->assertSee('Générer avec l\'IA', false);
    }
}
