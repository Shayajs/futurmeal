<?php

namespace App\Services\Budget;

use App\Enums\PriceSource;
use Illuminate\Support\Facades\Cache;

class OpenPricesService
{
    public function __construct(
        private OpenPricesClient $client,
    ) {}

    public function pricePerKgForBarcode(string $barcode, ?int $locationId = null): ?PriceResolution
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }

        $cacheKey = 'open_prices:'.$barcode.':'.($locationId ?? 'all');
        $ttl = (int) config('futurmeal.open_prices_cache_ttl', 86400);

        return Cache::remember($cacheKey, $ttl, function () use ($barcode, $locationId) {
            if ($locationId !== null) {
                $items = $this->client->fetchPrices($barcode, $locationId);
                $resolution = $this->resolveFromItems($items, $barcode, preferLatest: true);

                if ($resolution !== null) {
                    return $resolution;
                }
            }

            $items = $this->client->fetchPrices($barcode);
            if ($items === []) {
                return null;
            }

            return $this->resolveFromItems($items, $barcode, preferLatest: false);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function resolveFromItems(array $items, string $barcode, bool $preferLatest): ?PriceResolution
    {
        $perKgValues = [];

        foreach ($items as $item) {
            $perKg = $this->normalizeToPricePerKg($item);
            if ($perKg === null) {
                continue;
            }

            $perKgValues[] = [
                'price_per_kg' => $perKg,
                'date' => $item['date'] ?? null,
                'location_label' => data_get($item, 'location.osm_name'),
            ];
        }

        if ($perKgValues === []) {
            return null;
        }

        if ($preferLatest) {
            usort($perKgValues, fn ($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
            $chosen = $perKgValues[0];
        } else {
            usort($perKgValues, fn ($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
            $recent = array_slice($perKgValues, 0, 5);
            $prices = array_column($recent, 'price_per_kg');
            $median = $this->median($prices);
            $chosen = [
                'price_per_kg' => $median,
                'date' => null,
                'location_label' => null,
            ];
        }

        return new PriceResolution(
            pricePerKg: round($chosen['price_per_kg'], 2),
            source: PriceSource::OpenPrices,
            locationLabel: $chosen['location_label'],
            date: $chosen['date'],
            barcode: $barcode,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function normalizeToPricePerKg(array $item): ?float
    {
        $pricePer = $item['price_per'] ?? null;
        if (is_array($pricePer)) {
            $value = $pricePer['price'] ?? $pricePer['value'] ?? null;
            $unit = strtolower((string) ($pricePer['unit'] ?? $pricePer['type'] ?? ''));

            if ($value !== null && $value > 0) {
                if (str_contains($unit, 'kg')) {
                    return (float) $value;
                }
                if (str_contains($unit, '100g') || str_contains($unit, '100 g')) {
                    return (float) $value * 10;
                }
            }
        }

        $price = $item['price'] ?? null;
        if ($price === null || $price <= 0) {
            return null;
        }

        $product = $item['product'] ?? [];
        $qty = $product['product_quantity'] ?? null;
        $unit = strtolower((string) ($product['product_quantity_unit'] ?? ''));

        if ($qty !== null && $qty > 0) {
            if ($unit === 'g') {
                return (float) $price / ((float) $qty / 1000);
            }
            if ($unit === 'kg') {
                return (float) $price / (float) $qty;
            }
            if ($unit === 'ml' || $unit === 'l') {
                $liters = $unit === 'ml' ? (float) $qty / 1000 : (float) $qty;

                return $liters > 0 ? (float) $price / $liters : null;
            }
        }

        return null;
    }

    /**
     * @param  array<int, float>  $values
     */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }
}
