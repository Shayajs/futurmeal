<?php

namespace App\Services\Budget;

use Illuminate\Support\Facades\Http;

class OpenPricesClient
{
    public function fetchPrices(string $productCode, ?int $locationId = null, int $pageSize = 20): array
    {
        $params = [
            'product_code' => $productCode,
            'page_size' => $pageSize,
        ];

        if ($locationId !== null) {
            $params['location_id'] = $locationId;
        }

        $response = Http::withHeaders([
            'User-Agent' => config('futurmeal.off_user_agent'),
        ])->get($this->baseUrl().'/api/v1/prices', $params);

        if (! $response->successful()) {
            return [];
        }

        return data_get($response->json(), 'items', []);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchLocations(string $query, ?string $city = null, string $country = 'FR', int $pageSize = 10): array
    {
        if (strlen(trim($query)) < 2) {
            return [];
        }

        $params = [
            'osm_name__like' => trim($query),
            'osm_address_country_code' => $country,
            'page_size' => $pageSize,
        ];

        if ($city !== null && trim($city) !== '') {
            $params['osm_address_city__like'] = trim($city);
        }

        $response = Http::withHeaders([
            'User-Agent' => config('futurmeal.off_user_agent'),
        ])->get($this->baseUrl().'/api/v1/locations', $params);

        if (! $response->successful()) {
            return [];
        }

        return data_get($response->json(), 'items', []);
    }

    private function baseUrl(): string
    {
        return rtrim(config('futurmeal.open_prices_base_url'), '/');
    }
}
