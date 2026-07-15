<?php

namespace Tests\Feature;

use App\Enums\ActivityLevel;
use App\Enums\FoodReferenceType;
use App\Enums\Gender;
use App\Enums\GoalType;
use App\Livewire\DayEditor;
use App\Livewire\NotificationCenter;
use App\Models\CiqualComposition;
use App\Models\CiqualFood;
use App\Models\CiqualNutrient;
use App\Models\Friendship;
use App\Models\MealPlan;
use App\Models\MealPlanEntry;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Social\PlanShareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PlanShareFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    private function setupUsers(): array
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        foreach ([$owner, $viewer] as $user) {
            UserProfile::create([
                'user_id' => $user->id,
                'gender' => Gender::Male,
                'birth_date' => '1990-01-01',
                'height_cm' => 180,
                'activity_level' => ActivityLevel::Moderate,
                'goal_type' => GoalType::WeightLoss,
                'planning_horizon_days' => 7,
                'daily_calorie_target' => $user->id === $viewer->id ? 1800 : 2000,
            ]);
        }

        Friendship::create(['user_id' => $viewer->id, 'friend_id' => $owner->id, 'status' => 'accepted']);

        return [$viewer, $owner];
    }

    private function ciqualFood(): CiqualFood
    {
        $food = CiqualFood::create(['alim_code' => 500, 'name_fr' => 'Poulet']);
        $kcal = CiqualNutrient::create(['code' => 'ENERGY_KCAL', 'name_fr' => 'Energie', 'unit' => 'kcal']);
        CiqualComposition::create([
            'ciqual_food_id' => $food->id,
            'ciqual_nutrient_id' => $kcal->id,
            'value_per_100g' => 200,
        ]);

        return $food;
    }

    public function test_dashboard_uses_friend_meals_with_viewer_targets(): void
    {
        [$viewer, $owner] = $this->setupUsers();
        $food = $this->ciqualFood();

        $plan = MealPlan::create([
            'user_id' => $owner->id,
            'name' => 'Plan',
            'starts_on' => today(),
            'ends_on' => today()->addDays(6),
        ]);

        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => today(),
            'meal_slot' => 'lunch',
            'reference_type' => FoodReferenceType::Ciqual,
            'reference_id' => $food->id,
            'label' => 'Poulet',
            'quantity_g' => 200,
        ]);

        $share = app(PlanShareService::class)->inviteToFollow($owner, $viewer, false);
        app(PlanShareService::class)->accept($viewer, $share->id);
        $viewer->profile->update(['plan_view_user_id' => $owner->id]);

        $data = app(\App\Services\Dashboard\DashboardService::class)->build($viewer);

        $this->assertTrue($data['plan_context']['is_supervising']);
        $this->assertSame(400, $data['today']['consumed']);
        $this->assertSame(1800, $data['today']['target']);
    }

    public function test_viewer_cannot_edit_friend_plan_without_permission(): void
    {
        [$viewer, $owner] = $this->setupUsers();
        $food = $this->ciqualFood();

        $share = app(PlanShareService::class)->inviteToFollow($owner, $viewer, false);
        app(PlanShareService::class)->accept($viewer, $share->id);

        $component = Livewire::actingAs($viewer)
            ->withQueryParams(['view' => $owner->id])
            ->test(DayEditor::class, ['date' => today()->toDateString()]);

        $this->assertFalse($component->get('canEdit'));

        $component->call('openSlot', 'lunch')
            ->call('selectFoodForAdd', FoodReferenceType::Ciqual->value, $food->id, 'Poulet', null)
            ->call('addFood');

        $this->assertSame(0, MealPlanEntry::count());
    }

    public function test_notification_center_accepts_plan_share(): void
    {
        [$viewer, $owner] = $this->setupUsers();

        $share = app(PlanShareService::class)->requestToFollow($viewer, $owner);
        Notification::assertSentTo($owner, \App\Notifications\PlanFollowRequestNotification::class);

        Livewire::actingAs($owner)
            ->test(NotificationCenter::class)
            ->set('acceptPlanCanEdit.'.$share->id, true)
            ->call('acceptPlanShare', $share->id);

        $this->assertTrue(app(PlanShareService::class)->canEditPlan($viewer, $owner));
    }
}
