<?php

namespace Tests\Unit;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DashboardService::class);
    }

    public function test_today_meals_returns_planned_entries(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id,
            'gender' => Gender::Male,
            'birth_date' => '1990-01-01',
            'height_cm' => 180,
            'activity_level' => ActivityLevel::Moderate,
            'goal_type' => GoalType::WeightLoss,
            'planning_horizon_days' => 7,
            'daily_calorie_target' => 2000,
            'calorie_adjustment' => -400,
        ]);

        $plan = MealPlan::create([
            'user_id' => $user->id,
            'name' => 'Plan',
            'starts_on' => today(),
            'ends_on' => today()->addDays(6),
        ]);

        $food = CiqualFood::create(['alim_code' => 999, 'name_fr' => 'Poulet']);
        $kcalNutrient = CiqualNutrient::create(['code' => 'ENERGY_KCAL', 'name_fr' => 'Energie', 'unit' => 'kcal']);
        CiqualComposition::create([
            'ciqual_food_id' => $food->id,
            'ciqual_nutrient_id' => $kcalNutrient->id,
            'value_per_100g' => 200,
        ]);

        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => today(),
            'meal_slot' => 'lunch',
            'reference_type' => FoodReferenceType::Ciqual,
            'reference_id' => $food->id,
            'label' => 'Poulet grillé',
            'quantity_g' => 150,
        ]);

        $data = $this->service->build($user);

        $this->assertCount(6, $data['today_meals']);
        $lunch = collect($data['today_meals'])->firstWhere('slot_key', 'lunch');
        $this->assertNotNull($lunch);
        $this->assertFalse($lunch['empty']);
        $this->assertStringContainsString('Poulet grillé', $lunch['name']);
        $this->assertEquals(300, $lunch['kcal']);
        $this->assertEquals(300, $data['today']['consumed']);
    }

    public function test_weekly_budget_included_in_build(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id,
            'gender' => Gender::Male,
            'birth_date' => '1990-01-01',
            'height_cm' => 180,
            'activity_level' => ActivityLevel::Moderate,
            'goal_type' => GoalType::WeightLoss,
            'planning_horizon_days' => 7,
            'daily_calorie_target' => 2000,
        ]);

        $data = $this->service->build($user);

        $this->assertArrayHasKey('weekly_budget', $data);
        $this->assertArrayHasKey('budget_overview', $data);
        $this->assertFalse($data['weekly_budget']['has_prices']);
        $this->assertArrayHasKey('month', $data['budget_overview']);
        $this->assertArrayHasKey('year', $data['budget_overview']);
    }

    public function test_program_context_empty_when_no_memberships(): void
    {
        $user = User::factory()->create();

        $data = $this->service->build($user);

        $this->assertSame([], $data['programs']);
    }
}
