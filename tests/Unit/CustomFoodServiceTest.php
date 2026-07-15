<?php

namespace Tests\Unit;

use App\Enums\FoodReferenceType;
use App\Models\FoodItem;
use App\Models\User;
use App\Services\Nutrition\CustomFoodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomFoodServiceTest extends TestCase
{
    use RefreshDatabase;

    private CustomFoodService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CustomFoodService::class);
    }

    public function test_create_food_item_with_energy(): void
    {
        $user = User::factory()->create();

        $item = $this->service->create($user, 'Mon bar', ['energy_kcal' => 350]);

        $this->assertSame('Mon bar', $item->name);
        $this->assertSame(FoodReferenceType::Custom, $item->reference_type);
        $this->assertSame($user->id, $item->user_id);
        $this->assertTrue($item->is_community);
        $this->assertSame(350.0, $item->energy_kcal);
    }

    public function test_create_derives_energy_from_macros(): void
    {
        $user = User::factory()->create();

        $item = $this->service->create($user, 'Mix', [
            'protein_g' => 10,
            'carbs_g' => 20,
            'fat_g' => 5,
        ]);

        $this->assertSame(165.0, $item->energy_kcal);
    }

    public function test_create_requires_name_and_macros(): void
    {
        $user = User::factory()->create();

        $this->expectException(ValidationException::class);
        $this->service->create($user, '   ', []);
    }

    public function test_create_private_food_when_not_shared(): void
    {
        $user = User::factory()->create();

        $item = $this->service->create(
            $user,
            'Privé',
            ['energy_kcal' => 100],
            shareWithCommunity: false,
        );

        $this->assertFalse($item->is_community);
    }

    public function test_create_returns_existing_off_item_for_barcode(): void
    {
        $user = User::factory()->create();
        $existing = FoodItem::create([
            'reference_type' => FoodReferenceType::OpenFoodFacts,
            'external_id' => '3017620422003',
            'name' => 'Nutella',
            'energy_kcal' => 539,
            'protein_g' => 6,
            'carbs_g' => 57,
            'fat_g' => 31,
        ]);

        $item = $this->service->create(
            $user,
            'Doublon',
            ['energy_kcal' => 100],
            barcode: '3017620422003',
        );

        $this->assertSame($existing->id, $item->id);
    }
}
