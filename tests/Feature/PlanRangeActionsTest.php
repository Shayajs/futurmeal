<?php

namespace Tests\Feature;

use App\Enums\FoodReferenceType;
use App\Enums\GoalType;
use App\Livewire\MealPlanner;
use App\Models\CiqualComposition;
use App\Models\CiqualFood;
use App\Models\CiqualNutrient;
use App\Models\MealPlanEntry;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Nutrition\MealPlannerService;
use App\Services\Plan\PlanRangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanRangeActionsTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedUser(): User
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        UserProfile::create([
            'user_id' => $user->id,
            'goal_type' => GoalType::WeightLoss->value,
            'planning_horizon_days' => 7,
            'daily_calorie_target' => 2000,
            'calorie_adjustment' => -400,
        ]);

        return $user;
    }

    private function createCiqualFood(): CiqualFood
    {
        $food = CiqualFood::create(['alim_code' => 100, 'name_fr' => 'Riz cuit']);
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

    public function test_clear_range_removes_entries(): void
    {
        $user = $this->onboardedUser();
        $plan = app(MealPlannerService::class)->ensureDefaultPlan($user);
        $food = $this->createCiqualFood();
        $d1 = now()->startOfWeek()->toDateString();
        $d2 = now()->startOfWeek()->addDay()->toDateString();

        foreach ([$d1, $d2] as $date) {
            MealPlanEntry::create([
                'meal_plan_id' => $plan->id,
                'planned_on' => $date,
                'meal_slot' => 'lunch',
                'reference_type' => FoodReferenceType::Ciqual,
                'reference_id' => $food->id,
                'label' => 'Riz cuit',
                'quantity_g' => 100,
            ]);
        }

        Livewire::actingAs($user)
            ->test(MealPlanner::class)
            ->call('openRangePanel', 'clear')
            ->set('rangeSourceStart', $d1)
            ->set('rangeSourceEnd', $d2)
            ->call('applyRangeAction');

        $this->assertSame(0, MealPlanEntry::where('meal_plan_id', $plan->id)->count());
    }

    public function test_copy_range_duplicates_days_to_target(): void
    {
        $user = $this->onboardedUser();
        $plan = app(MealPlannerService::class)->ensureDefaultPlan($user);
        $food = $this->createCiqualFood();
        $src = now()->startOfWeek()->toDateString();
        $src2 = now()->startOfWeek()->addDay()->toDateString();
        $target = now()->startOfWeek()->addDays(3)->toDateString();
        $target2 = now()->startOfWeek()->addDays(4)->toDateString();

        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => $src,
            'meal_slot' => 'breakfast',
            'reference_type' => FoodReferenceType::Ciqual,
            'reference_id' => $food->id,
            'label' => 'Riz cuit',
            'quantity_g' => 80,
        ]);
        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => $src2,
            'meal_slot' => 'dinner',
            'reference_type' => FoodReferenceType::Ciqual,
            'reference_id' => $food->id,
            'label' => 'Riz cuit',
            'quantity_g' => 120,
        ]);

        Livewire::actingAs($user)
            ->test(MealPlanner::class)
            ->call('openRangePanel', 'copy')
            ->set('rangeSourceStart', $src)
            ->set('rangeSourceEnd', $src2)
            ->set('rangeTargetStart', $target)
            ->call('applyRangeAction');

        $this->assertTrue(
            MealPlanEntry::query()
                ->where('meal_plan_id', $plan->id)
                ->whereDate('planned_on', $target)
                ->where('meal_slot', 'breakfast')
                ->where('quantity_g', 80)
                ->exists()
        );
        $this->assertTrue(
            MealPlanEntry::query()
                ->where('meal_plan_id', $plan->id)
                ->whereDate('planned_on', $target2)
                ->where('meal_slot', 'dinner')
                ->where('quantity_g', 120)
                ->exists()
        );
    }

    public function test_plan_range_service_rejects_inverted_dates(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(PlanRangeService::class)->datesBetween('2026-07-20', '2026-07-10');
    }
}
