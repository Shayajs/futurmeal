<?php

namespace App\Services\Nutrition;

use App\Data\NutrientProfile;
use App\Enums\FoodReferenceType;
use App\Models\CiqualFood;
use App\Models\FoodItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Support\Facades\Http;

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
}
