<?php

namespace Tests\Unit;

use App\Enums\FoodReferenceType;
use App\Models\FoodItem;
use App\Services\Nutrition\OpenFoodFactsClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenFoodFactsClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_barcode_prefers_french_product_name(): void
    {
        Http::fake([
            'world.openfoodfacts.org/api/v3/product/*' => Http::response([
                'product' => [
                    'product_name' => 'كريمة طازجة',
                    'product_name_fr' => 'Crème fraîche 7%',
                    'product_name_en' => 'Fresh cream 7%',
                    'brands' => 'Elle & Vire',
                    'nutriments' => [
                        'energy-kcal_100g' => 140,
                        'proteins_100g' => 2.5,
                        'carbohydrates_100g' => 3.5,
                        'fat_100g' => 7,
                    ],
                ],
            ]),
        ]);

        $item = app(OpenFoodFactsClient::class)->fetchByBarcode('1234567890123');

        $this->assertNotNull($item);
        $this->assertSame('Crème fraîche 7%', $item->name);
        $this->assertSame(FoodReferenceType::OpenFoodFacts, $item->reference_type);
    }

    public function test_barcode_refreshes_arabic_cached_name(): void
    {
        FoodItem::create([
            'reference_type' => FoodReferenceType::OpenFoodFacts,
            'external_id' => '1234567890123',
            'name' => 'كريمة طازجة',
            'energy_kcal' => 100,
            'protein_g' => 1,
            'carbs_g' => 1,
            'fat_g' => 1,
        ]);

        Http::fake([
            'world.openfoodfacts.org/api/v3/product/*' => Http::response([
                'product' => [
                    'product_name' => 'كريمة طازجة',
                    'product_name_fr' => 'Crème fraîche',
                    'nutriments' => [
                        'energy-kcal_100g' => 140,
                        'proteins_100g' => 2.5,
                        'carbohydrates_100g' => 3.5,
                        'fat_100g' => 7,
                    ],
                ],
            ]),
        ]);

        $item = app(OpenFoodFactsClient::class)->fetchByBarcode('1234567890123');

        $this->assertSame('Crème fraîche', $item->name);
        $this->assertSame(1, FoodItem::query()->count());
    }

    public function test_search_prefers_french_and_skips_arabic_generic_name(): void
    {
        Http::fake([
            'world.openfoodfacts.org/api/v2/search*' => Http::response([
                'products' => [[
                    'code' => '3017620422003',
                    'product_name' => 'منتج تجريبي',
                    'product_name_fr' => 'Nutella',
                    'brands' => 'Ferrero',
                    'nutriments' => [
                        'energy-kcal_100g' => 539,
                        'proteins_100g' => 6,
                        'carbohydrates_100g' => 57,
                        'fat_100g' => 31,
                    ],
                ]],
            ]),
        ]);

        $results = app(OpenFoodFactsClient::class)->searchByText('nutella', 5);

        $this->assertCount(1, $results);
        $this->assertStringContainsString('Nutella', $results[0]['label']);
        $this->assertDatabaseHas('food_items', [
            'external_id' => '3017620422003',
            'name' => 'Nutella',
        ]);
    }
}
