<?php

namespace Tests\Unit;

use App\Data\AiWeekPlanDraft;
use App\Data\AiWeekPlanItemDraft;
use App\Enums\FoodReferenceType;
use App\Models\FoodItem;
use App\Models\MealPlan;
use App\Models\MealPlanEntry;
use App\Models\User;
use App\Services\Ai\AiWeekPlanApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiWeekPlanApplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_applies_ai_estimate_as_custom_food_with_price(): void
    {
        $user = User::factory()->create();
        $plan = MealPlan::create([
            'user_id' => $user->id,
            'name' => 'Plan',
            'starts_on' => '2026-07-20',
            'ends_on' => '2026-07-26',
        ]);

        $draft = new AiWeekPlanDraft([
            new AiWeekPlanItemDraft(
                date: '2026-07-20',
                slot: 'breakfast',
                label: 'Bol mystère IA',
                quantityG: 200.0,
                recipeId: null,
                referenceType: null,
                referenceId: null,
                foodItemId: null,
                resolved: true,
                warning: 'Estimations IA',
                matchKind: 'ai_estimate',
                proteinG: 40.0,
                carbsG: 20.0,
                fatG: 10.0,
                energyKcal: 330.0,
                priceEur: 3.4,
            ),
        ]);

        $result = app(AiWeekPlanApplier::class)->apply(
            $user,
            $plan->id,
            '2026-07-20',
            1,
            $draft,
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['skipped']);

        $entry = MealPlanEntry::query()->where('meal_plan_id', $plan->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(200.0, $entry->quantity_g);
        $this->assertSame(3.4, $entry->estimated_cost);
        $this->assertSame(FoodReferenceType::Custom, $entry->reference_type);

        $food = FoodItem::query()->find($entry->food_item_id);
        $this->assertNotNull($food);
        $this->assertSame('Bol mystère IA', $food->name);
        // Macros portion → per 100 g (×0.5)
        $this->assertSame(20.0, $food->protein_g);
        $this->assertSame(165.0, $food->energy_kcal);
        $this->assertFalse($food->is_community);
    }
}
