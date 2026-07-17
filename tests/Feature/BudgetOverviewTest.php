<?php

namespace Tests\Feature;

use App\Enums\ActivityLevel;
use App\Enums\FoodReferenceType;
use App\Enums\Gender;
use App\Enums\GoalType;
use App\Livewire\BudgetManager;
use App\Livewire\DashboardStats;
use App\Livewire\MealPlanner;
use App\Models\BudgetEntry;
use App\Models\MealPlan;
use App\Models\MealPlanEntry;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Nutrition\MealPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPlanAndCosts(): User
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
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
            'weekly_budget_target' => 50,
        ]);

        $plan = app(MealPlannerService::class)->ensureDefaultPlan($user);
        $weekStart = now()->startOfWeek();

        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => $weekStart,
            'meal_slot' => 'lunch',
            'label' => 'Poulet',
            'quantity_g' => 200,
            'estimated_cost' => 4.5,
        ]);
        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => $weekStart->copy()->addDay(),
            'meal_slot' => 'dinner',
            'label' => 'Riz',
            'quantity_g' => 150,
            'estimated_cost' => 1.5,
        ]);

        BudgetEntry::create([
            'user_id' => $user->id,
            'label' => 'Poulet',
            'reference_type' => FoodReferenceType::Custom,
            'price_per_kg' => 10,
        ]);

        return $user;
    }

    public function test_budget_manager_saves_weekly_target(): void
    {
        $user = $this->userWithPlanAndCosts();

        Livewire::actingAs($user)
            ->test(BudgetManager::class)
            ->assertSet('weekly_budget_target', 50.0)
            ->set('weekly_budget_target', 85.5)
            ->call('saveTarget')
            ->assertHasNoErrors();

        $this->assertEquals(85.5, (float) $user->fresh()->profile->weekly_budget_target);
    }

    public function test_dashboard_shows_budget_overview(): void
    {
        $user = $this->userWithPlanAndCosts();

        Livewire::actingAs($user)
            ->test(DashboardStats::class)
            ->assertSee('Budget estimé')
            ->assertSee('6,00')
            ->assertSee('Cible semaine');
    }

    public function test_meal_planner_shows_period_cost(): void
    {
        $user = $this->userWithPlanAndCosts();
        $weekStart = now()->startOfWeek()->toDateString();

        Livewire::actingAs($user)
            ->test(MealPlanner::class, ['weekStart' => $weekStart])
            ->assertSee('Coût période')
            ->assertSee('6,00');
    }
}
