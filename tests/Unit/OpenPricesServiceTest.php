<?php

namespace Tests\Unit;

use App\Enums\FoodReferenceType;
use App\Enums\PriceSource;
use App\Models\BudgetEntry;
use App\Models\FoodItem;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Budget\BudgetService;
use App\Services\Budget\OpenPricesService;
use App\Services\Budget\PriceResolution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenPricesServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_grams_to_price_per_kg(): void
    {
        $service = app(OpenPricesService::class);

        $perKg = $service->normalizeToPricePerKg([
            'price' => 3.2,
            'product' => [
                'product_quantity' => 400,
                'product_quantity_unit' => 'g',
            ],
        ]);

        $this->assertEquals(8.0, $perKg);
    }

    public function test_price_per_kg_for_barcode_uses_store_when_available(): void
    {
        Cache::flush();

        Http::fake([
            'prices.openfoodfacts.org/api/v1/prices*location_id=33*' => Http::response([
                'items' => [[
                    'price' => 4.0,
                    'date' => '2024-06-01',
                    'product' => ['product_quantity' => 400, 'product_quantity_unit' => 'g'],
                    'location' => ['osm_name' => 'Carrefour Test'],
                ]],
            ]),
        ]);

        $resolution = app(OpenPricesService::class)->pricePerKgForBarcode('3017620422003', 33);

        $this->assertInstanceOf(PriceResolution::class, $resolution);
        $this->assertEquals(10.0, $resolution->pricePerKg);
        $this->assertSame(PriceSource::OpenPrices, $resolution->source);
        $this->assertSame('Carrefour Test', $resolution->locationLabel);
    }

    public function test_price_per_kg_uses_cache(): void
    {
        Cache::flush();

        Http::fake([
            'prices.openfoodfacts.org/api/v1/prices*' => Http::response([
                'items' => [[
                    'price' => 2.0,
                    'date' => '2024-01-01',
                    'product' => ['product_quantity' => 500, 'product_quantity_unit' => 'g'],
                    'location' => ['osm_name' => 'Test'],
                ]],
            ]),
        ]);

        $service = app(OpenPricesService::class);
        $service->pricePerKgForBarcode('1234567890123');
        $service->pricePerKgForBarcode('1234567890123');

        Http::assertSentCount(1);
    }
}
