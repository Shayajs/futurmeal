<?php

namespace Tests\Feature;

use App\Enums\ActivityLevel;
use App\Enums\FoodReferenceType;
use App\Enums\Gender;
use App\Enums\GoalType;
use App\Enums\ShoppingItemSource;
use App\Livewire\ShoppingList;
use App\Models\MealPlan;
use App\Models\MealPlanEntry;
use App\Models\ShoppingListItem;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShoppingListTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedUserWithFood(): User
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
            'target_weight_kg' => 75,
            'target_body_fat_percent' => 15,
        ]);

        $weekStart = now()->startOfWeek();
        $plan = MealPlan::create([
            'user_id' => $user->id,
            'name' => 'Plan',
            'starts_on' => $weekStart,
            'ends_on' => $weekStart->copy()->addDays(6),
        ]);

        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => $weekStart,
            'meal_slot' => 'lunch',
            'label' => 'Poulet',
            'reference_type' => FoodReferenceType::Custom,
            'quantity_g' => 250,
            'sort_order' => 0,
        ]);
        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => $weekStart->copy()->addDay(),
            'meal_slot' => 'dinner',
            'label' => 'Poulet',
            'reference_type' => FoodReferenceType::Custom,
            'quantity_g' => 150,
            'sort_order' => 0,
        ]);

        return $user;
    }

    public function test_page_prefills_aggregated_items(): void
    {
        $user = $this->onboardedUserWithFood();

        Livewire::actingAs($user)
            ->test(ShoppingList::class)
            ->assertSee('Faire mes courses')
            ->assertSee('Poulet')
            ->assertSee('400');

        $this->assertDatabaseCount('shopping_list_items', 1);
        $this->assertSame(400.0, (float) ShoppingListItem::first()->quantity_g);
    }

    public function test_check_persists_and_custom_add_resync_keeps_check(): void
    {
        $user = $this->onboardedUserWithFood();

        $component = Livewire::actingAs($user)
            ->test(ShoppingList::class);

        $itemId = ShoppingListItem::first()->id;

        $component
            ->call('toggleItem', $itemId, true)
            ->assertHasNoErrors();

        $this->assertTrue(ShoppingListItem::find($itemId)->is_checked);

        $component
            ->set('customLabel', 'Éponge')
            ->set('customQuantityG', null)
            ->call('addCustom')
            ->assertHasNoErrors()
            ->assertSee('Éponge');

        $this->assertDatabaseHas('shopping_list_items', [
            'label' => 'Éponge',
            'source' => ShoppingItemSource::Custom->value,
        ]);

        $component->call('resync')->assertSee('resynchronisée');

        $this->assertTrue(ShoppingListItem::find($itemId)->fresh()->is_checked);
        $this->assertDatabaseHas('shopping_list_items', [
            'label' => 'Éponge',
            'source' => ShoppingItemSource::Custom->value,
        ]);
    }

    public function test_shopping_route_renders(): void
    {
        $user = $this->onboardedUserWithFood();

        $this->actingAs($user)->get('/shopping')->assertOk();
    }
}
