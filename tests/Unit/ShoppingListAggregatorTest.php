<?php

namespace Tests\Unit;

use App\Enums\FoodReferenceType;
use App\Models\MealPlan;
use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Shopping\ShoppingListAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShoppingListAggregatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregates_duplicate_food_lines(): void
    {
        $user = User::factory()->create();
        $plan = MealPlan::create([
            'user_id' => $user->id,
            'name' => 'Plan',
            'starts_on' => '2026-07-13',
            'ends_on' => '2026-07-19',
        ]);

        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => '2026-07-13',
            'meal_slot' => 'lunch',
            'label' => 'Poulet',
            'reference_type' => FoodReferenceType::Custom,
            'quantity_g' => 200,
            'sort_order' => 0,
        ]);
        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => '2026-07-14',
            'meal_slot' => 'dinner',
            'label' => 'Poulet',
            'reference_type' => FoodReferenceType::Custom,
            'quantity_g' => 200,
            'sort_order' => 0,
        ]);

        $rows = app(ShoppingListAggregator::class)->aggregate(
            $user,
            \Carbon\Carbon::parse('2026-07-13'),
            \Carbon\Carbon::parse('2026-07-19'),
        );

        $this->assertCount(1, $rows);
        $this->assertSame('Poulet', $rows[0]['label']);
        $this->assertEquals(400.0, $rows[0]['quantity_g']);
    }

    public function test_scales_recipe_ingredients_by_portions(): void
    {
        $user = User::factory()->create();
        $plan = MealPlan::create([
            'user_id' => $user->id,
            'name' => 'Plan',
            'starts_on' => '2026-07-13',
            'ends_on' => '2026-07-19',
        ]);

        $recipe = Recipe::create([
            'user_id' => $user->id,
            'name' => 'Poulet riz',
            'servings' => 1,
            'is_macro_preset' => false,
        ]);
        $recipe->ingredients()->create([
            'label' => 'Riz',
            'reference_type' => FoodReferenceType::Custom,
            'quantity_g' => 100,
            'sort_order' => 0,
        ]);

        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => '2026-07-13',
            'meal_slot' => 'lunch',
            'recipe_id' => $recipe->id,
            'portions' => 2,
            'sort_order' => 0,
        ]);

        $rows = app(ShoppingListAggregator::class)->aggregate(
            $user,
            \Carbon\Carbon::parse('2026-07-13'),
            \Carbon\Carbon::parse('2026-07-19'),
        );

        $this->assertCount(1, $rows);
        $this->assertSame('Riz', $rows[0]['label']);
        $this->assertEquals(200.0, $rows[0]['quantity_g']);
    }

    public function test_merges_same_label_across_different_food_item_ids(): void
    {
        $user = User::factory()->create();
        $plan = MealPlan::create([
            'user_id' => $user->id,
            'name' => 'Plan',
            'starts_on' => '2026-07-13',
            'ends_on' => '2026-07-19',
        ]);

        $a = \App\Models\FoodItem::create([
            'reference_type' => FoodReferenceType::Custom,
            'name' => 'Yaourt nature',
            'energy_kcal' => 50,
            'protein_g' => 4,
            'carbs_g' => 5,
            'fat_g' => 1,
        ]);
        $b = \App\Models\FoodItem::create([
            'reference_type' => FoodReferenceType::Custom,
            'name' => 'Yaourt nature',
            'energy_kcal' => 50,
            'protein_g' => 4,
            'carbs_g' => 5,
            'fat_g' => 1,
        ]);

        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => '2026-07-13',
            'meal_slot' => 'breakfast',
            'label' => 'Yaourt nature',
            'food_item_id' => $a->id,
            'reference_type' => FoodReferenceType::Custom,
            'quantity_g' => 125,
            'sort_order' => 0,
        ]);
        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => '2026-07-14',
            'meal_slot' => 'breakfast',
            'label' => 'Yaourt nature · perso',
            'food_item_id' => $b->id,
            'reference_type' => FoodReferenceType::Custom,
            'quantity_g' => 125,
            'sort_order' => 0,
        ]);

        $rows = app(ShoppingListAggregator::class)->aggregate(
            $user,
            \Carbon\Carbon::parse('2026-07-13'),
            \Carbon\Carbon::parse('2026-07-19'),
        );

        $this->assertCount(1, $rows);
        $this->assertSame('Yaourt nature', $rows[0]['label']);
        $this->assertEquals(250.0, $rows[0]['quantity_g']);
        $this->assertSame('label:yaourt nature', $rows[0]['key']);
    }
}
