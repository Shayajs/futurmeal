<?php

namespace App\Services\Nutrition;

use App\Enums\FoodReferenceType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class FoodSearchService
{
    public function __construct(
        private NutritionResolver $resolver,
        private OpenFoodFactsClient $offClient,
    ) {}

    /**
     * @return array{results: array<int, array<string, mixed>>, can_create_custom: bool, query: string}
     */
    public function search(string $query, ?User $user = null, int $limit = 10): array
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return ['results' => [], 'can_create_custom' => false, 'query' => $query];
        }

        if ($this->looksLikeBarcode($query)) {
            $item = $this->offClient->fetchByBarcode($query);
            if ($item) {
                return [
                    'results' => [[
                        'type' => FoodReferenceType::OpenFoodFacts->value,
                        'id' => $item->id,
                        'label' => $item->name.($item->brand ? " ({$item->brand})" : ''),
                        'barcode' => $item->external_id,
                    ]],
                    'can_create_custom' => false,
                    'query' => $query,
                ];
            }

            return [
                'results' => [],
                'can_create_custom' => true,
                'query' => $query,
                'barcode_not_found' => true,
            ];
        }

        $results = $this->resolver->searchFoods($query, $user ?? Auth::user(), $limit);

        return [
            'results' => $results,
            'can_create_custom' => $results === [],
            'query' => $query,
        ];
    }

    private function looksLikeBarcode(string $query): bool
    {
        return (bool) preg_match('/^\d{8,14}$/', $query);
    }
}
