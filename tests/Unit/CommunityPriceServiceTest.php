<?php

namespace Tests\Unit;

use App\Data\ProductReference;
use App\Enums\FoodReferenceType;
use App\Enums\PriceSource;
use App\Models\CommunityStorePrice;
use App\Models\FoodItem;
use App\Models\User;
use App\Services\Budget\CommunityPriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityPriceServiceTest extends TestCase
{
    use RefreshDatabase;

    private CommunityPriceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CommunityPriceService::class);
    }

    public function test_contribute_upserts_user_contribution(): void
    {
        $user = User::factory()->create();
        $product = new ProductReference(
            referenceType: FoodReferenceType::Ciqual->value,
            referenceId: 42,
            label: 'Riz cuit',
        );

        $first = $this->service->contribute($user, $product, 'Carrefour', 4.5);
        $second = $this->service->contribute($user, $product, 'Carrefour', 5.0);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(5.0, $second->price_per_kg);
        $this->assertDatabaseCount('community_store_prices', 1);
    }

    public function test_median_for_product_filters_by_store_brand(): void
    {
        $users = User::factory()->count(3)->create();
        $product = new ProductReference(
            referenceType: FoodReferenceType::Ciqual->value,
            referenceId: 10,
            label: 'Poulet',
        );

        $this->service->contribute($users[0], $product, 'Leclerc', 8.0);
        $this->service->contribute($users[1], $product, 'Leclerc', 10.0);
        $this->service->contribute($users[2], $product, 'Carrefour', 20.0);

        $leclerc = $this->service->medianForProduct('Leclerc', $product);
        $carrefour = $this->service->medianForProduct('Carrefour', $product);

        $this->assertNotNull($leclerc);
        $this->assertSame(9.0, $leclerc->pricePerKg);
        $this->assertSame(PriceSource::Community, $leclerc->source);
        $this->assertSame(2, $leclerc->contributionCount);

        $this->assertNotNull($carrefour);
        $this->assertSame(20.0, $carrefour->pricePerKg);
        $this->assertSame(1, $carrefour->contributionCount);
    }

    public function test_median_returns_null_when_no_contributions(): void
    {
        $product = new ProductReference(label: 'Inconnu');

        $this->assertNull($this->service->medianForProduct('Lidl', $product));
    }

    public function test_brands_for_product_lists_distinct_brands(): void
    {
        $users = User::factory()->count(2)->create();
        $food = FoodItem::create([
            'reference_type' => FoodReferenceType::Custom,
            'name' => 'Barre protéinée',
            'energy_kcal' => 400,
            'protein_g' => 20,
            'carbs_g' => 30,
            'fat_g' => 10,
        ]);
        $product = new ProductReference(
            referenceType: FoodReferenceType::Custom->value,
            referenceId: $food->id,
            foodItemId: $food->id,
            label: 'Barre protéinée',
        );

        $this->service->contribute($users[0], $product, 'Auchan', 12.0);
        $this->service->contribute($users[1], $product, 'Leclerc', 11.0);

        $brands = $this->service->brandsForProduct($product);

        $this->assertSame(['Auchan', 'Leclerc'], $brands);
    }

    public function test_delete_contribution_only_for_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $product = new ProductReference(label: 'Yaourt');

        $entry = $this->service->contribute($owner, $product, 'Intermarché', 3.5);

        $this->service->deleteContribution($other, $entry->id);
        $this->assertDatabaseHas('community_store_prices', ['id' => $entry->id]);

        $this->service->deleteContribution($owner, $entry->id);
        $this->assertDatabaseMissing('community_store_prices', ['id' => $entry->id]);
    }
}
