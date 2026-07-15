<?php

namespace App\Services\Nutrition;

use App\Data\NutrientProfile;
use App\Enums\FoodReferenceType;
use App\Models\CiqualFood;
use App\Models\FoodItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class OpenFoodFactsClient
{
    private const BASE_URL = 'https://world.openfoodfacts.org';

    public function fetchByBarcode(string $barcode): ?FoodItem
    {
        $cached = FoodItem::query()
            ->where('reference_type', FoodReferenceType::OpenFoodFacts)
            ->where('external_id', $barcode)
            ->first();

        if ($cached) {
            return $cached;
        }

        $response = Http::withHeaders([
            'User-Agent' => config('futurmeal.off_user_agent'),
        ])->get(self::BASE_URL."/api/v3/product/{$barcode}", [
            'fields' => 'product_name,brands,nutriments,image_url',
        ]);

        if (! $response->successful()) {
            return null;
        }

        $product = data_get($response->json(), 'product');

        if (! $product) {
            return null;
        }

        $nutriments = $product['nutriments'] ?? [];

        return FoodItem::create([
            'reference_type' => FoodReferenceType::OpenFoodFacts,
            'external_id' => $barcode,
            'name' => $product['product_name'] ?? 'Produit inconnu',
            'brand' => $product['brands'] ?? null,
            'energy_kcal' => (float) ($nutriments['energy-kcal_100g'] ?? ($nutriments['energy_100g'] ?? 0) / 4.184),
            'protein_g' => (float) ($nutriments['proteins_100g'] ?? 0),
            'carbs_g' => (float) ($nutriments['carbohydrates_100g'] ?? 0),
            'fat_g' => (float) ($nutriments['fat_100g'] ?? 0),
            'fiber_g' => (float) ($nutriments['fiber_100g'] ?? 0),
            'salt_g' => (float) ($nutriments['salt_100g'] ?? 0),
            'raw_nutriments' => $nutriments,
        ]);
    }

    /**
     * @return array<int, array{type: string, id: int, label: string, barcode: string|null}>
     */
    public function searchByText(string $query, int $limit = 10): array
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }

        $rateKey = 'off-search:'.(request()->ip() ?? 'cli');
        if (RateLimiter::tooManyAttempts($rateKey, config('futurmeal.off_search_rate_limit'))) {
            return [];
        }

        RateLimiter::hit($rateKey, 60);

        $cacheKey = 'off_search_'.md5(mb_strtolower($query)).'_'.$limit;

        return Cache::remember($cacheKey, config('futurmeal.off_search_cache_ttl'), function () use ($query, $limit) {
            $response = Http::withHeaders([
                'User-Agent' => config('futurmeal.off_user_agent'),
            ])->get(self::BASE_URL.'/api/v2/search', [
                'search_terms' => $query,
                'fields' => 'code,product_name,brands,nutriments',
                'page_size' => $limit,
            ]);

            if (! $response->successful()) {
                return [];
            }

            $products = data_get($response->json(), 'products', []);

            return collect($products)
                ->filter(fn ($product) => ! empty($product['code']))
                ->take($limit)
                ->map(function (array $product) {
                    $item = $this->upsertFromSearchResult($product);

                    return [
                        'type' => FoodReferenceType::OpenFoodFacts->value,
                        'id' => $item->id,
                        'label' => $item->name.($item->brand ? " ({$item->brand})" : '').' · OFF',
                        'barcode' => $item->external_id,
                    ];
                })
                ->values()
                ->all();
        });
    }

    private function upsertFromSearchResult(array $product): FoodItem
    {
        $barcode = (string) $product['code'];
        $nutriments = $product['nutriments'] ?? [];

        return FoodItem::updateOrCreate(
            [
                'reference_type' => FoodReferenceType::OpenFoodFacts,
                'external_id' => $barcode,
            ],
            [
                'name' => $product['product_name'] ?? 'Produit inconnu',
                'brand' => $product['brands'] ?? null,
                'energy_kcal' => (float) ($nutriments['energy-kcal_100g'] ?? ($nutriments['energy_100g'] ?? 0) / 4.184),
                'protein_g' => (float) ($nutriments['proteins_100g'] ?? 0),
                'carbs_g' => (float) ($nutriments['carbohydrates_100g'] ?? 0),
                'fat_g' => (float) ($nutriments['fat_100g'] ?? 0),
                'fiber_g' => (float) ($nutriments['fiber_100g'] ?? 0),
                'salt_g' => (float) ($nutriments['salt_100g'] ?? 0),
                'raw_nutriments' => $nutriments,
            ]
        );
    }
}
