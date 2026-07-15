<?php

namespace Tests\Unit;

use App\Enums\FoodReferenceType;
use App\Models\CiqualComposition;
use App\Models\CiqualFood;
use App\Models\CiqualNutrient;
use App\Models\MealPlan;
use App\Models\MealPlanEntry;
use App\Models\User;
use App\Services\Social\PublishedMenuService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishedMenuTest extends TestCase
{
    use RefreshDatabase;

    private PublishedMenuService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PublishedMenuService::class);
    }

    private function createPlanWithDay(User $user, string $date): MealPlan
    {
        $plan = MealPlan::create([
            'user_id' => $user->id,
            'name' => 'Plan',
            'starts_on' => $date,
            'ends_on' => $date,
        ]);

        $food = CiqualFood::create(['alim_code' => 300, 'name_fr' => 'Œuf']);
        $kcal = CiqualNutrient::create(['code' => 'ENERGY_KCAL', 'name_fr' => 'Energie', 'unit' => 'kcal']);
        CiqualComposition::create([
            'ciqual_food_id' => $food->id,
            'ciqual_nutrient_id' => $kcal->id,
            'value_per_100g' => 140,
        ]);

        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => $date,
            'meal_slot' => 'breakfast',
            'reference_type' => FoodReferenceType::Ciqual,
            'reference_id' => $food->id,
            'label' => 'Œufs brouillés',
            'quantity_g' => 120,
        ]);

        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => $date,
            'meal_slot' => 'lunch',
            'reference_type' => FoodReferenceType::Ciqual,
            'reference_id' => $food->id,
            'label' => 'Omelette',
            'quantity_g' => 200,
        ]);

        return $plan;
    }

    public function test_publish_day_creates_snapshot_with_kcal(): void
    {
        $user = User::factory()->create();
        $date = today()->toDateString();
        $plan = $this->createPlanWithDay($user, $date);

        $menu = $this->service->publishDay($user, $plan->id, $date, 'Journée œufs', 'Riche en protéines');

        $this->assertNotNull($menu);
        $this->assertSame('Journée œufs', $menu->title);
        $this->assertTrue($menu->is_public);
        $this->assertArrayHasKey('breakfast', $menu->day_snapshot);
        $this->assertArrayHasKey('lunch', $menu->day_snapshot);
        $this->assertSame('Œufs brouillés', $menu->day_snapshot['breakfast'][0]['label']);
        $this->assertSame(168, $menu->day_snapshot['breakfast'][0]['kcal']);
        $this->assertSame(168 + 280, $menu->totalKcal());
    }

    public function test_publish_empty_day_returns_null(): void
    {
        $user = User::factory()->create();
        $plan = MealPlan::create([
            'user_id' => $user->id,
            'name' => 'Plan',
            'starts_on' => today(),
            'ends_on' => today(),
        ]);

        $menu = $this->service->publishDay($user, $plan->id, today()->toDateString(), 'Vide');

        $this->assertNull($menu);
    }

    public function test_apply_to_day_copies_snapshot_and_increments_counter(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        $date = today()->toDateString();

        $authorPlan = $this->createPlanWithDay($author, $date);
        $menu = $this->service->publishDay($author, $authorPlan->id, $date, 'Journée œufs');

        $readerPlan = MealPlan::create([
            'user_id' => $reader->id,
            'name' => 'Plan',
            'starts_on' => $date,
            'ends_on' => $date,
        ]);

        $targetDate = today()->addDays(2)->toDateString();
        $created = $this->service->applyToDay($reader, $menu, $readerPlan->id, $targetDate);

        $this->assertSame(2, $created);
        $this->assertSame(1, $menu->fresh()->copies_count);

        $entry = MealPlanEntry::where('meal_plan_id', $readerPlan->id)
            ->whereDate('planned_on', $targetDate)
            ->where('meal_slot', 'breakfast')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame('Œufs brouillés', $entry->label);
        $this->assertSame(120.0, $entry->quantity_g);
    }

    public function test_apply_to_day_replaces_existing_entries(): void
    {
        $author = User::factory()->create();
        $date = today()->toDateString();
        $plan = $this->createPlanWithDay($author, $date);
        $menu = $this->service->publishDay($author, $plan->id, $date, 'Journée œufs');

        $targetDate = today()->addDay()->toDateString();
        MealPlanEntry::create([
            'meal_plan_id' => $plan->id,
            'planned_on' => $targetDate,
            'meal_slot' => 'dinner',
            'label' => 'Vieux plat',
            'quantity_g' => 100,
        ]);

        $this->service->applyToDay($author, $menu, $plan->id, $targetDate);

        $this->assertDatabaseMissing('meal_plan_entries', ['label' => 'Vieux plat']);
    }
}
