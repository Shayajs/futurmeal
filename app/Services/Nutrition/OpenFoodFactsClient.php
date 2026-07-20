<?php

namespace App\Services\Nutrition;

use App\Enums\FoodReferenceType;
use App\Models\FoodItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class OpenFoodFactsClient
{
    private const BASE_URL = 'https://world.openfoodfacts.org';

    private const PRODUCT_FIELDS = 'product_name,product_name_fr,product_name_en,brands,nutriments,image_url';

    private const SEARCH_FIELDS = 'code,product_name,product_name_fr,product_name_en,brands,nutriments';

    public function fetchByBarcode(string $barcode): ?FoodItem
    {
        $cached = FoodItem::query()
            ->where('reference_type', FoodReferenceType::OpenFoodFacts)
            ->where('external_id', $barcode)
            ->first();

        if ($cached && ! $this->needsFrenchNameRefresh($cached->name)) {
            return $cached;
        }

        $response = Http::withHeaders($this->headers())->get(self::BASE_URL."/api/v3/product/{$barcode}", [
            'fields' => self::PRODUCT_FIELDS,
        ]);

        if (! $response->successful()) {
            return $cached;
        }

        $product = data_get($response->json(), 'product');

        if (! $product) {
            return $cached;
        }

        return $this->upsertFromProduct($barcode, $product);
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

        $cacheKey = 'off_search_fr_v2_'.md5(mb_strtolower($query)).'_'.$limit;

        return Cache::remember($cacheKey, config('futurmeal.off_search_cache_ttl'), function () use ($query, $limit) {
            $response = Http::withHeaders($this->headers())->get(self::BASE_URL.'/api/v2/search', [
                'search_terms' => $query,
                'fields' => self::SEARCH_FIELDS,
                'page_size' => $limit,
                'countries_tags_en' => 'france',
                'lc' => 'fr',
                'cc' => 'fr',
            ]);

            if (! $response->successful()) {
                return [];
            }

            $products = data_get($response->json(), 'products', []);

            // Si trop peu de résultats FR, élargir sans filtre pays.
            if (count($products) < max(3, (int) ceil($limit / 2))) {
                $fallback = Http::withHeaders($this->headers())->get(self::BASE_URL.'/api/v2/search', [
                    'search_terms' => $query,
                    'fields' => self::SEARCH_FIELDS,
                    'page_size' => $limit,
                    'lc' => 'fr',
                    'cc' => 'fr',
                ]);

                if ($fallback->successful()) {
                    $products = data_get($fallback->json(), 'products', $products);
                }
            }

            return collect($products)
                ->filter(fn ($product) => ! empty($product['code']))
                ->unique('code')
                ->take($limit)
                ->map(function (array $product) {
                    $item = $this->upsertFromProduct((string) $product['code'], $product);

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

    /**
     * @param  array<string, mixed>  $product
     */
    private function upsertFromProduct(string $barcode, array $product): FoodItem
    {
        $nutriments = $product['nutriments'] ?? [];

        return FoodItem::updateOrCreate(
            [
                'reference_type' => FoodReferenceType::OpenFoodFacts,
                'external_id' => $barcode,
            ],
            [
                'name' => $this->resolveLocalizedName($product),
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

    /**
     * @param  array<string, mixed>  $product
     */
    private function resolveLocalizedName(array $product): string
    {
        $candidates = [
            $product['product_name_fr'] ?? null,
            $product['product_name'] ?? null,
            $product['product_name_en'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $name = trim((string) $candidate);
            if ($name === '') {
                continue;
            }

            if ($this->looksNonLatinDominant($name)) {
                continue;
            }

            return $name;
        }

        foreach ($candidates as $candidate) {
            $name = trim((string) $candidate);
            if ($name !== '') {
                return $name;
            }
        }

        return 'Produit inconnu';
    }

    private function needsFrenchNameRefresh(?string $name): bool
    {
        $name = trim((string) $name);

        return $name === '' || $name === 'Produit inconnu' || $this->looksNonLatinDominant($name);
    }

    /**
     * Détecte un nom majoritairement en écriture non latine (arabe, etc.).
     */
    private function looksNonLatinDominant(string $name): bool
    {
        if (preg_match('/\p{Arabic}/u', $name)) {
            return true;
        }

        $letters = preg_replace('/[^\p{L}]/u', '', $name) ?? '';
        if ($letters === '') {
            return false;
        }

        $latin = preg_replace('/[^\p{Latin}]/u', '', $letters) ?? '';

        return mb_strlen($latin) < (mb_strlen($letters) / 2);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'User-Agent' => config('futurmeal.off_user_agent'),
            'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.5',
        ];
    }
}
