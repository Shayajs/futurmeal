<?php

namespace Tests\Unit;

use App\Enums\ActivityLevel;
use App\Enums\FoodReferenceType;
use App\Enums\Gender;
use App\Enums\GoalType;
use App\Models\BodyMetric;
use App\Models\CiqualComposition;
use App\Models\CiqualFood;
use App\Models\CiqualNutrient;
use App\Models\MealPlan;
use App\Models\MealPlanEntry;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Charts\ChartSeriesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartSeriesServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChartSeriesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ChartSeriesService::class);
    }

    private function createProfile(User $user, int $target = 2000): void
    {
        UserProfile::create([
            'user_id' => $user->id,
            'gender' => Gender::Male,
            'birth_date' => '1990-01-01',
            'height_cm' => 180,
            'activity_level' => ActivityLevel::Moderate,
            'goal_type' => GoalType::WeightLoss,
            'planning_horizon_days' => 7,
            'daily_calorie_target' => $target,
            'calorie_adjustment' => -400,
        ]);
    }

    public function test_body_series_returns_dated_values_within_period(): void
    {
        $user = User::factory()->create();

        BodyMetric::create(['user_id' => $user->id, 'recorded_at' => now()->subDays(2), 'weight_kg' => 82.5]);
        BodyMetric::create(['user_id' => $user->id, 'recorded_at' => now()->subDay(), 'weight_kg' => 82.1]);
        BodyMetric::create(['user_id' => $user->id, 'recorded_at' => now()->subDays(40), 'weight_kg' => 85.0]);

        [$from, $to] = $this->service->periodRange($user, '7d');
        $series = $this->service->series($user, 'weight_kg', $from, $to);

        $this->assertCount(2, $series);
        $this->assertEquals(82.5, $series[now()->subDays(2)->toDateString()]);
        $this->assertEquals(82.1, $series[now()->subDay()->toDateString()]);
    }

    public function test_planned_kcal_and_deficit_series(): void
    {
        $user = User::factory()->create();
        $this->createProfile($user, 2000);

        $plan = MealPlan::create([
            'user_id' => $user->id,
            'name' => 'Plan',
            'starts_on' => today(),
            'ends_on' => today()->addDays(6),
        ]);

        $food = CiqualFood::create(['alim_code' => 200, 'name_fr' => 'Pâtes']);
        $kcal = CiqualNutrient::create(['code' => 'ENERGY_KCAL', 'name_fr' => 'Energie', 'unit' => 'kcal']);
        CiqualComposition::create([
            'ciqual_food_id' => $food->id,
            'ciqual_nutrient_id' => $kcal->id,
            'value_per_100g' => 150,
        ]);

        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => today(),
            'meal_slot' => 'lunch',
            'reference_type' => FoodReferenceType::Ciqual,
            'reference_id' => $food->id,
            'label' => 'Pâtes',
            'quantity_g' => 200,
        ]);

        [$from, $to] = $this->service->periodRange($user, '7d');

        $planned = $this->service->series($user, 'planned_kcal', $from, $to);
        $this->assertEquals(300, $planned[today()->toDateString()]);

        $deficit = $this->service->series($user, 'deficit_kcal', $from, $to);
        $this->assertEquals(1700, $deficit[today()->toDateString()]);
    }

    public function test_budget_series_sums_estimated_costs_per_day(): void
    {
        $user = User::factory()->create();
        $this->createProfile($user);

        $plan = MealPlan::create([
            'user_id' => $user->id,
            'name' => 'Plan',
            'starts_on' => today(),
            'ends_on' => today()->addDays(6),
        ]);

        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => today(),
            'meal_slot' => 'lunch',
            'label' => 'A',
            'quantity_g' => 100,
            'estimated_cost' => 2.50,
        ]);
        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => today(),
            'meal_slot' => 'dinner',
            'label' => 'B',
            'quantity_g' => 100,
            'estimated_cost' => 1.25,
        ]);

        [$from, $to] = $this->service->periodRange($user, '30d');
        $series = $this->service->series($user, 'budget_eur', $from, $to);

        $this->assertEquals(3.75, $series[today()->toDateString()]);
    }

    public function test_all_period_starts_at_earliest_data_point(): void
    {
        $user = User::factory()->create();
        BodyMetric::create(['user_id' => $user->id, 'recorded_at' => now()->subDays(100), 'weight_kg' => 90.0]);

        [$from] = $this->service->periodRange($user, 'all');

        $this->assertTrue($from->lte(now()->subDays(100)));

        $series = $this->service->series($user, 'weight_kg', $from, now()->endOfDay());
        $this->assertCount(1, $series);
    }
}
