<?php

namespace Tests\Unit;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use App\Enums\GoalType;
use App\Enums\ProgramMemberRole;
use App\Enums\FoodReferenceType;
use App\Enums\PriceSource;
use App\Models\BudgetEntry;
use App\Models\FoodItem;
use App\Models\MealPlan;
use App\Models\MealPlanEntry;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\Recipe;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Budget\BudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BudgetServiceTest extends TestCase
{
    use RefreshDatabase;

    private BudgetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BudgetService::class);
    }

    public function test_calculate_recipe_cost_from_price_per_kg(): void
    {
        $user = User::factory()->create();

        $recipe = Recipe::create([
            'user_id' => $user->id,
            'name' => 'Poulet riz',
            'servings' => 1,
            'is_macro_preset' => true,
            'preset_energy_kcal' => 500,
            'preset_protein_g' => 40,
            'preset_carbs_g' => 50,
            'preset_fat_g' => 10,
        ]);

        $this->assertNull($this->service->calculateRecipeCost($user, $recipe));

        $recipe->update(['is_macro_preset' => false]);
        $recipe->ingredients()->create([
            'reference_type' => FoodReferenceType::Custom,
            'label' => 'Poulet',
            'quantity_g' => 200,
            'sort_order' => 0,
        ]);

        BudgetEntry::create([
            'user_id' => $user->id,
            'label' => 'Poulet',
            'reference_type' => FoodReferenceType::Custom,
            'price_per_kg' => 10.0,
        ]);

        $cost = $this->service->calculateRecipeCost($user, $recipe->fresh(['ingredients']), 1);
        $this->assertEquals(2.0, $cost);
    }

    public function test_weekly_total_sums_estimated_costs(): void
    {
        $user = User::factory()->create();
        $plan = MealPlan::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'starts_on' => now()->startOfWeek(),
            'ends_on' => now()->startOfWeek()->addDays(6),
        ]);

        $recipe = Recipe::create([
            'user_id' => $user->id,
            'name' => 'Repas',
            'is_macro_preset' => true,
            'preset_energy_kcal' => 400,
        ]);

        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => now()->startOfWeek(),
            'meal_slot' => 'lunch',
            'recipe_id' => $recipe->id,
            'portions' => 1,
            'estimated_cost' => 5.50,
        ]);

        BudgetEntry::create([
            'user_id' => $user->id,
            'label' => 'Test',
            'reference_type' => FoodReferenceType::Custom,
            'price_per_kg' => 5,
        ]);

        $weekly = $this->service->weeklyTotal($user);
        $this->assertTrue($weekly['has_prices']);
        $this->assertEquals(5.5, $weekly['spent']);
        $this->assertEquals(1, $weekly['entry_count']);
    }

    public function test_resolve_price_prefers_user_price_over_open_prices(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id,
            'open_prices_location_id' => 33,
        ]);

        $offItem = FoodItem::create([
            'reference_type' => FoodReferenceType::OpenFoodFacts,
            'external_id' => '3017620422003',
            'name' => 'Nutella',
            'energy_kcal' => 539,
            'protein_g' => 6,
            'carbs_g' => 57,
            'fat_g' => 31,
        ]);

        BudgetEntry::create([
            'user_id' => $user->id,
            'label' => 'Nutella',
            'food_item_id' => $offItem->id,
            'reference_type' => FoodReferenceType::OpenFoodFacts,
            'reference_id' => $offItem->id,
            'price_per_kg' => 12.0,
            'price_source' => PriceSource::User,
        ]);

        Http::fake();

        $resolution = $this->service->resolvePrice(
            $user,
            FoodReferenceType::OpenFoodFacts->value,
            $offItem->id,
            'Nutella',
            '3017620422003',
            33,
        );

        $this->assertSame(12.0, $resolution->pricePerKg);
        $this->assertSame(PriceSource::User, $resolution->source);
        Http::assertNothingSent();
    }

    public function test_resolve_price_falls_back_to_open_prices(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'prices.openfoodfacts.org/api/v1/prices*' => Http::response([
                'items' => [[
                    'price' => 3.0,
                    'date' => '2024-03-01',
                    'product' => ['product_quantity' => 300, 'product_quantity_unit' => 'g'],
                    'location' => ['osm_name' => 'Lidl'],
                ]],
            ]),
        ]);

        $resolution = $this->service->resolvePrice(
            $user,
            FoodReferenceType::OpenFoodFacts->value,
            1,
            'Produit test',
            '3017620422003',
        );

        $this->assertNotNull($resolution);
        $this->assertSame(PriceSource::OpenPrices, $resolution->source);
        $this->assertEquals(10.0, $resolution->pricePerKg);
    }

    public function test_resolve_price_prefers_brand_personal_over_global_and_community(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $food = FoodItem::create([
            'reference_type' => FoodReferenceType::Custom,
            'name' => 'Barre',
            'energy_kcal' => 400,
            'protein_g' => 20,
            'carbs_g' => 30,
            'fat_g' => 10,
        ]);

        BudgetEntry::create([
            'user_id' => $user->id,
            'label' => 'Barre',
            'food_item_id' => $food->id,
            'reference_type' => FoodReferenceType::Custom,
            'reference_id' => $food->id,
            'price_per_kg' => 9.0,
            'store_brand' => 'Leclerc',
        ]);

        BudgetEntry::create([
            'user_id' => $user->id,
            'label' => 'Barre',
            'food_item_id' => $food->id,
            'reference_type' => FoodReferenceType::Custom,
            'reference_id' => $food->id,
            'price_per_kg' => 11.0,
        ]);

        app(\App\Services\Budget\CommunityPriceService::class)->contribute(
            $other,
            new \App\Data\ProductReference(
                referenceType: FoodReferenceType::Custom->value,
                referenceId: $food->id,
                foodItemId: $food->id,
                label: 'Barre',
            ),
            'Leclerc',
            7.0,
        );

        $brandResolution = $this->service->resolvePrice(
            $user,
            FoodReferenceType::Custom->value,
            $food->id,
            'Barre',
            null,
            null,
            'Leclerc',
        );

        $this->assertSame(9.0, $brandResolution->pricePerKg);
        $this->assertSame(PriceSource::User, $brandResolution->source);

        BudgetEntry::where('user_id', $user->id)->where('store_brand', 'Leclerc')->delete();

        $globalBeforeCommunity = $this->service->resolvePrice(
            $user,
            FoodReferenceType::Custom->value,
            $food->id,
            'Barre',
            null,
            null,
            'Leclerc',
        );

        $this->assertSame(11.0, $globalBeforeCommunity->pricePerKg);
        $this->assertSame(PriceSource::User, $globalBeforeCommunity->source);

        BudgetEntry::where('user_id', $user->id)->whereNull('store_brand')->delete();

        $communityResolution = $this->service->resolvePrice(
            $user,
            FoodReferenceType::Custom->value,
            $food->id,
            'Barre',
            null,
            null,
            'Leclerc',
        );

        $this->assertSame(7.0, $communityResolution->pricePerKg);
        $this->assertSame(PriceSource::Community, $communityResolution->source);
    }
}
