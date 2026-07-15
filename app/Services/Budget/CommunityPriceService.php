<?php

namespace App\Services\Budget;

use App\Data\ProductReference;
use App\Enums\PriceSource;
use App\Models\CommunityStorePrice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CommunityPriceService
{
    public function contribute(
        User $user,
        ProductReference $product,
        string $storeBrand,
        float $pricePerKg,
        ?int $locationId = null,
        ?Carbon $observedAt = null,
    ): CommunityStorePrice {
        $storeBrand = trim($storeBrand);
        $query = CommunityStorePrice::query()
            ->where('user_id', $user->id)
            ->where('store_brand', $storeBrand);

        $this->applyProductScope($query, $product);

        $entry = $query->first();

        $payload = [
            'reference_type' => $product->referenceType,
            'reference_id' => $product->referenceId,
            'food_item_id' => $product->foodItemId,
            'label' => $product->label ?? '',
            'barcode' => $product->barcode,
            'store_brand' => $storeBrand,
            'open_prices_location_id' => $locationId,
            'price_per_kg' => round($pricePerKg, 2),
            'observed_at' => ($observedAt ?? now())->toDateString(),
        ];

        if ($entry) {
            $entry->update($payload);

            return $entry->fresh();
        }

        return CommunityStorePrice::create([
            'user_id' => $user->id,
            ...$payload,
        ]);
    }

    public function medianForProduct(
        string $storeBrand,
        ProductReference $product,
        int $maxAgeDays = 90,
    ): ?PriceResolution {
        $storeBrand = trim($storeBrand);
        if ($storeBrand === '') {
            return null;
        }

        $since = now()->subDays($maxAgeDays)->toDateString();

        $query = CommunityStorePrice::query()
            ->where('store_brand', $storeBrand)
            ->where('observed_at', '>=', $since);

        $this->applyProductScope($query, $product);

        $prices = $query->pluck('price_per_kg')->map(fn ($p) => (float) $p)->all();

        if ($prices === []) {
            return null;
        }

        sort($prices);
        $median = $this->median($prices);

        return new PriceResolution(
            pricePerKg: round($median, 2),
            source: PriceSource::Community,
            locationLabel: $storeBrand,
            barcode: $product->barcode,
            contributionCount: count($prices),
        );
    }

    /**
     * @return array<int, string>
     */
    public function brandsForProduct(ProductReference $product, int $maxAgeDays = 90): array
    {
        $since = now()->subDays($maxAgeDays)->toDateString();

        $query = CommunityStorePrice::query()
            ->where('observed_at', '>=', $since);

        $this->applyProductScope($query, $product);

        return $query->distinct()
            ->orderBy('store_brand')
            ->pluck('store_brand')
            ->all();
    }

    public function contributionsFor(User $user): Collection
    {
        return CommunityStorePrice::where('user_id', $user->id)
            ->orderByDesc('observed_at')
            ->orderBy('label')
            ->get();
    }

    public function deleteContribution(User $user, int $id): void
    {
        CommunityStorePrice::where('user_id', $user->id)->where('id', $id)->delete();
    }

    private function applyProductScope($query, ProductReference $product): void
    {
        if ($product->foodItemId) {
            $query->where('food_item_id', $product->foodItemId);

            return;
        }

        if ($product->referenceType && $product->referenceId) {
            $query->where('reference_type', $product->referenceType)
                ->where('reference_id', $product->referenceId);

            return;
        }

        if ($product->label) {
            $query->where('label', $product->label);
        }
    }

    /**
     * @param  array<int, float>  $values
     */
    private function median(array $values): float
    {
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }
}
