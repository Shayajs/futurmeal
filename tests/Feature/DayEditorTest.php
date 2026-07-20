<?php

namespace Tests\Feature;

use App\Enums\FoodReferenceType;
use App\Enums\ProgramMemberRole;
use App\Livewire\DayEditor;
use App\Models\BudgetEntry;
use App\Models\CiqualComposition;
use App\Models\CiqualFood;
use App\Models\CiqualNutrient;
use App\Models\FoodItem;
use App\Models\MealPlanEntry;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class DayEditorTest extends TestCase
{
    use RefreshDatabase;

    private function createCiqualFood(): CiqualFood
    {
        $food = CiqualFood::create(['alim_code' => 100, 'name_fr' => 'Riz cuit']);
        $kcal = CiqualNutrient::create(['code' => 'ENERGY_KCAL', 'name_fr' => 'Energie', 'unit' => 'kcal']);
        CiqualComposition::create([
            'ciqual_food_id' => $food->id,
            'ciqual_nutrient_id' => $kcal->id,
            'value_per_100g' => 130,
        ]);

        return $food;
    }

    private function selectAndAddFood($component, string $type, int $id, string $label, ?string $barcode = null)
    {
        return $component
            ->call('selectFoodForAdd', $type, $id, $label, $barcode)
            ->call('addFood');
    }

    public function test_user_can_add_food_to_a_slot(): void
    {
        $user = User::factory()->create();
        $food = $this->createCiqualFood();
        $date = today()->toDateString();

        Livewire::actingAs($user)
            ->test(DayEditor::class, ['date' => $date])
            ->call('openSlot', 'lunch')
            ->set('quantityG', 180)
            ->tap(fn ($c) => $this->selectAndAddFood($c, FoodReferenceType::Ciqual->value, $food->id, 'Riz cuit'));

        $entry = MealPlanEntry::whereDate('planned_on', $date)->where('meal_slot', 'lunch')->first();

        $this->assertNotNull($entry);
        $this->assertSame(FoodReferenceType::Ciqual, $entry->reference_type);
        $this->assertSame($food->id, $entry->reference_id);
        $this->assertSame('Riz cuit', $entry->label);
        $this->assertSame(180.0, $entry->quantity_g);
    }

    public function test_optional_price_per_kg_is_saved_and_applied_to_entry_cost(): void
    {
        $user = User::factory()->create();
        $food = $this->createCiqualFood();
        $date = today()->toDateString();

        Livewire::actingAs($user)
            ->test(DayEditor::class, ['date' => $date])
            ->call('openSlot', 'lunch')
            ->set('quantityG', 200)
            ->call('selectFoodForAdd', FoodReferenceType::Ciqual->value, $food->id, 'Riz cuit', null)
            ->set('pricePerKg', 10)
            ->call('addFood');

        $entry = MealPlanEntry::whereDate('planned_on', $date)->first();

        $this->assertNotNull($entry);
        $this->assertSame(2.0, $entry->estimated_cost);
        $this->assertDatabaseHas('budget_entries', [
            'user_id' => $user->id,
            'label' => 'Riz cuit',
            'price_per_kg' => 10,
        ]);
    }

    public function test_cent_precision_price_is_saved_and_applied_to_entry_cost(): void
    {
        $user = User::factory()->create();
        $food = $this->createCiqualFood();
        $date = today()->toDateString();

        Livewire::actingAs($user)
            ->test(DayEditor::class, ['date' => $date])
            ->call('openSlot', 'lunch')
            ->set('quantityG', 200)
            ->call('selectFoodForAdd', FoodReferenceType::Ciqual->value, $food->id, 'Crème fraîche 7%', null)
            ->set('pricePerKg', 4.93)
            ->call('addFood');

        $entry = MealPlanEntry::whereDate('planned_on', $date)->first();

        $this->assertNotNull($entry);
        $this->assertSame(0.99, $entry->estimated_cost);
        $this->assertDatabaseHas('budget_entries', [
            'user_id' => $user->id,
            'label' => 'Crème fraîche 7%',
            'price_per_kg' => 4.93,
        ]);
    }

    public function test_select_food_prefills_saved_user_price(): void
    {
        $user = User::factory()->create();
        $food = $this->createCiqualFood();

        BudgetEntry::create([
            'user_id' => $user->id,
            'label' => 'Riz cuit',
            'reference_type' => FoodReferenceType::Ciqual,
            'reference_id' => $food->id,
            'price_per_kg' => 7.5,
        ]);

        Livewire::actingAs($user)
            ->test(DayEditor::class, ['date' => today()->toDateString()])
            ->call('openSlot', 'lunch')
            ->call('selectFoodForAdd', FoodReferenceType::Ciqual->value, $food->id, 'Riz cuit', null)
            ->assertSet('pricePerKg', 7.5)
            ->assertSet('priceSource', 'user')
            ->assertSet('priceSourceLabel', 'Ton prix enregistré');
    }

    public function test_select_food_prefills_open_prices_when_no_user_price(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id,
            'open_prices_location_id' => 33,
            'open_prices_location_label' => 'Carrefour Test',
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

        Http::fake([
            'prices.openfoodfacts.org/api/v1/prices*' => Http::response([
                'items' => [[
                    'price' => 3.2,
                    'date' => '2024-01-11',
                    'product' => [
                        'product_quantity' => 400,
                        'product_quantity_unit' => 'g',
                    ],
                    'location' => ['osm_name' => 'Carrefour Test'],
                ]],
            ]),
        ]);

        Livewire::actingAs($user)
            ->test(DayEditor::class, ['date' => today()->toDateString()])
            ->call('openSlot', 'lunch')
            ->call('selectFoodForAdd', FoodReferenceType::OpenFoodFacts->value, $offItem->id, 'Nutella', '3017620422003')
            ->assertSet('pricePerKg', 8.0)
            ->assertSet('priceSource', 'open_prices');
    }

    public function test_suggestion_applies_saved_price(): void
    {
        $user = User::factory()->create();
        $food = $this->createCiqualFood();

        BudgetEntry::create([
            'user_id' => $user->id,
            'label' => 'Riz cuit',
            'reference_type' => FoodReferenceType::Ciqual,
            'reference_id' => $food->id,
            'price_per_kg' => 5,
        ]);

        Livewire::actingAs($user)
            ->test(DayEditor::class, ['date' => today()->toDateString()])
            ->call('addSuggestion', 'lunch', FoodReferenceType::Ciqual->value, $food->id, 'Riz cuit', 300);

        $entry = MealPlanEntry::whereDate('planned_on', today())->first();
        $this->assertSame(1.5, $entry->estimated_cost);
    }

    public function test_copy_day_duplicates_entries_to_selected_days(): void
    {
        $user = User::factory()->create();
        $food = $this->createCiqualFood();
        $monday = now()->startOfWeek();

        $component = Livewire::actingAs($user)
            ->test(DayEditor::class, ['date' => $monday->toDateString()])
            ->call('openSlot', 'breakfast');

        $this->selectAndAddFood($component, FoodReferenceType::Ciqual->value, $food->id, 'Riz cuit');

        $targets = [
            $monday->copy()->addDay()->toDateString(),
            $monday->copy()->addDays(2)->toDateString(),
        ];

        $component
            ->set('copyTargets', $targets)
            ->call('copyDay');

        foreach ($targets as $target) {
            $this->assertTrue(
                MealPlanEntry::whereDate('planned_on', $target)
                    ->where('meal_slot', 'breakfast')
                    ->where('label', 'Riz cuit')
                    ->exists(),
                "Entrée manquante pour le {$target}",
            );
        }

        $this->assertSame(3, MealPlanEntry::where('label', 'Riz cuit')->count());
    }

    public function test_copy_day_replaces_existing_entries_on_target(): void
    {
        $user = User::factory()->create();
        $food = $this->createCiqualFood();
        $monday = now()->startOfWeek();
        $tuesday = $monday->copy()->addDay()->toDateString();

        $component = Livewire::actingAs($user)
            ->test(DayEditor::class, ['date' => $monday->toDateString()])
            ->call('openSlot', 'lunch');

        $this->selectAndAddFood($component, FoodReferenceType::Ciqual->value, $food->id, 'Riz cuit');

        MealPlanEntry::create([
            'meal_plan_id' => $component->get('mealPlanId'),
            'planned_on' => $tuesday,
            'meal_slot' => 'dinner',
            'reference_type' => FoodReferenceType::Ciqual,
            'reference_id' => $food->id,
            'label' => 'Ancien plat',
            'quantity_g' => 100,
        ]);

        $component
            ->set('copyTargets', [$tuesday])
            ->call('copyDay');

        $this->assertDatabaseMissing('meal_plan_entries', ['label' => 'Ancien plat']);
        $this->assertTrue(
            MealPlanEntry::whereDate('planned_on', $tuesday)->where('label', 'Riz cuit')->exists(),
        );
    }

    public function test_member_cannot_edit_locked_program_day(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $food = $this->createCiqualFood();

        $program = Program::create([
            'owner_id' => $owner->id,
            'name' => 'Cut',
            'lock_portions' => true,
            'week_starts_on' => now()->startOfWeek(),
        ]);
        ProgramMember::create(['program_id' => $program->id, 'user_id' => $owner->id, 'role' => ProgramMemberRole::Owner]);
        ProgramMember::create(['program_id' => $program->id, 'user_id' => $member->id, 'role' => ProgramMemberRole::Member]);

        $component = Livewire::actingAs($member)
            ->withQueryParams(['program' => $program->id])
            ->test(DayEditor::class, ['date' => today()->toDateString()]);

        $this->assertFalse($component->get('canEdit'));

        $component->call('openSlot', 'lunch');
        $this->selectAndAddFood($component, FoodReferenceType::Ciqual->value, $food->id, 'Riz cuit');

        $this->assertDatabaseMissing('meal_plan_entries', ['label' => 'Riz cuit']);
    }

    public function test_empty_search_allows_custom_food_creation_and_meal_entry(): void
    {
        $user = User::factory()->create();
        $date = today()->toDateString();

        Livewire::actingAs($user)
            ->test(DayEditor::class, ['date' => $date])
            ->call('openSlot', 'lunch')
            ->set('foodSearch', 'Barre maison')
            ->assertSet('canCreateCustomFood', true)
            ->call('openCustomFoodPanel')
            ->set('customFoodName', 'Barre maison')
            ->set('customFoodEnergy', 420)
            ->set('customFoodProtein', 25)
            ->call('createCustomFood')
            ->assertSet('selectedFoodLabel', 'Barre maison')
            ->set('quantityG', 50)
            ->call('addFood');

        $entry = MealPlanEntry::whereDate('planned_on', $date)->first();
        $this->assertNotNull($entry);
        $this->assertSame(FoodReferenceType::Custom, $entry->reference_type);
        $this->assertDatabaseHas('food_items', [
            'user_id' => $user->id,
            'name' => 'Barre maison',
            'is_community' => true,
        ]);
    }

    public function test_price_with_store_brand_contributes_to_community(): void
    {
        $user = User::factory()->create();
        $food = $this->createCiqualFood();
        $date = today()->toDateString();

        Livewire::actingAs($user)
            ->test(DayEditor::class, ['date' => $date])
            ->call('openSlot', 'lunch')
            ->set('storeBrand', 'Carrefour')
            ->set('sharePriceWithCommunity', true)
            ->call('selectFoodForAdd', FoodReferenceType::Ciqual->value, $food->id, 'Riz cuit', null)
            ->set('pricePerKg', 6.5)
            ->call('addFood');

        $this->assertDatabaseHas('community_store_prices', [
            'user_id' => $user->id,
            'label' => 'Riz cuit',
            'store_brand' => 'Carrefour',
            'price_per_kg' => 6.5,
        ]);

        $entry = MealPlanEntry::whereDate('planned_on', $date)->first();
        $this->assertNotNull($entry);
        $this->assertSame(0.65, $entry->estimated_cost);
    }

    public function test_user_can_edit_food_entry_after_adding(): void
    {
        $user = User::factory()->create();
        $food = $this->createCiqualFood();
        $date = today()->toDateString();

        $component = Livewire::actingAs($user)
            ->test(DayEditor::class, ['date' => $date])
            ->call('openSlot', 'lunch')
            ->set('quantityG', 200)
            ->call('selectFoodForAdd', FoodReferenceType::Ciqual->value, $food->id, 'Crème fraîche 7%', null)
            ->set('pricePerKg', 5.0)
            ->call('addFood');

        $entry = MealPlanEntry::whereDate('planned_on', $date)->first();
        $this->assertSame(1.0, $entry->estimated_cost);

        $component
            ->call('openEntryEdit', $entry->id)
            ->set('editQuantityG', 150)
            ->set('editPricePerKg', 4.93)
            ->set('editStoreBrand', 'Leclerc')
            ->call('saveEntryEdit');

        $entry->refresh();

        $this->assertSame(150.0, $entry->quantity_g);
        $this->assertSame(0.74, $entry->estimated_cost);
        $this->assertDatabaseHas('budget_entries', [
            'user_id' => $user->id,
            'label' => 'Crème fraîche 7%',
            'price_per_kg' => 4.93,
            'store_brand' => 'Leclerc',
        ]);
    }

    public function test_select_food_prefills_community_median(): void
    {
        $user = User::factory()->create();
        $contributor = User::factory()->create();
        $food = $this->createCiqualFood();

        app(\App\Services\Budget\CommunityPriceService::class)->contribute(
            $contributor,
            new \App\Data\ProductReference(
                referenceType: FoodReferenceType::Ciqual->value,
                referenceId: $food->id,
                label: 'Riz cuit',
            ),
            'Leclerc',
            8.0,
        );
        app(\App\Services\Budget\CommunityPriceService::class)->contribute(
            User::factory()->create(),
            new \App\Data\ProductReference(
                referenceType: FoodReferenceType::Ciqual->value,
                referenceId: $food->id,
                label: 'Riz cuit',
            ),
            'Leclerc',
            10.0,
        );

        Livewire::actingAs($user)
            ->test(DayEditor::class, ['date' => today()->toDateString()])
            ->call('openSlot', 'lunch')
            ->set('storeBrand', 'Leclerc')
            ->call('selectFoodForAdd', FoodReferenceType::Ciqual->value, $food->id, 'Riz cuit', null)
            ->assertSet('pricePerKg', 9.0)
            ->assertSet('priceSource', 'community')
            ->assertSet('priceSourceLabel', 'Communauté · Leclerc (2 relevés)');
    }
}
