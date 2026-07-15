<?php

namespace App\Services\Budget;

use App\Data\ProductReference;
use App\Enums\FoodReferenceType;
use App\Enums\PriceSource;
use App\Models\BudgetEntry;
use App\Models\FoodItem;
use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BudgetService
{
    public function __construct(
        private OpenPricesService $openPrices,
        private CommunityPriceService $communityPrices,
    ) {}

    public function upsert(
        User $user,
        string $label,
        float $pricePerKg,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?int $foodItemId = null,
        PriceSource $priceSource = PriceSource::User,
        ?string $storeBrand = null,
        bool $shareWithCommunity = false,
        ?int $locationId = null,
        ?Carbon $observedAt = null,
    ): BudgetEntry {
        $query = BudgetEntry::query()->where('user_id', $user->id);

        if ($storeBrand !== null && trim($storeBrand) !== '') {
            $query->where('store_brand', trim($storeBrand));
        } else {
            $query->whereNull('store_brand');
        }

        if ($foodItemId) {
            $query->where('food_item_id', $foodItemId);
        } elseif ($referenceType && $referenceId) {
            $query->where('reference_type', $referenceType)->where('reference_id', $referenceId);
        } else {
            $query->where('label', $label);
        }

        $entry = $query->first();

        $brand = $storeBrand !== null && trim($storeBrand) !== '' ? trim($storeBrand) : null;

        $payload = [
            'label' => $label,
            'price_per_kg' => $pricePerKg,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'food_item_id' => $foodItemId,
            'price_source' => $priceSource,
            'store_brand' => $brand,
        ];

        if ($entry) {
            $entry->update($payload);
        } else {
            $entry = BudgetEntry::create([
                'user_id' => $user->id,
                ...$payload,
                'reference_type' => $referenceType ?? FoodReferenceType::Custom->value,
            ]);
        }

        if ($shareWithCommunity && $brand !== null && $pricePerKg > 0) {
            $this->communityPrices->contribute(
                $user,
                new ProductReference(
                    referenceType: $referenceType,
                    referenceId: $referenceId,
                    foodItemId: $foodItemId,
                    label: $label,
                    barcode: FoodItem::query()->whereKey($foodItemId)->value('external_id'),
                ),
                $brand,
                $pricePerKg,
                $locationId,
                $observedAt,
            );
        }

        return $entry;
    }

    public function resolvePrice(
        User $user,
        ?string $type,
        ?int $id,
        string $label,
        ?string $barcode = null,
        ?int $locationId = null,
        ?string $storeBrand = null,
    ): ?PriceResolution {
        $referenceType = FoodReferenceType::tryFrom($type ?? '');
        $foodItemId = $referenceType && in_array($referenceType, [FoodReferenceType::OpenFoodFacts, FoodReferenceType::Custom], true)
            ? $id
            : null;

        $product = new ProductReference(
            referenceType: $referenceType?->value,
            referenceId: $id,
            foodItemId: $foodItemId,
            label: $label,
            barcode: $barcode,
        );

        if ($storeBrand === null) {
            $storeBrand = $this->preferredStoreBrand($user);
        }

        if ($storeBrand !== null && trim($storeBrand) !== '') {
            $brandPrice = $this->priceForReference(
                $user,
                $referenceType?->value,
                $id,
                $label,
                $foodItemId,
                trim($storeBrand),
            );

            if ($brandPrice !== null) {
                return new PriceResolution(
                    pricePerKg: round($brandPrice, 2),
                    source: PriceSource::User,
                    locationLabel: trim($storeBrand),
                    barcode: $barcode,
                );
            }
        }

        $userPrice = $this->priceForReference($user, $referenceType?->value, $id, $label, $foodItemId);
        if ($userPrice !== null) {
            return new PriceResolution(
                pricePerKg: round($userPrice, 2),
                source: PriceSource::User,
                barcode: $barcode,
            );
        }

        if ($storeBrand !== null && trim($storeBrand) !== '') {
            $community = $this->communityPrices->medianForProduct(trim($storeBrand), $product);
            if ($community !== null) {
                return $community;
            }
        }

        if ($barcode === null && $foodItemId !== null) {
            $barcode = FoodItem::query()->whereKey($foodItemId)->value('external_id');
            $product = new ProductReference(
                referenceType: $referenceType?->value,
                referenceId: $id,
                foodItemId: $foodItemId,
                label: $label,
                barcode: $barcode,
            );
        }

        if ($barcode === null || trim($barcode) === '') {
            return null;
        }

        return $this->openPrices->pricePerKgForBarcode($barcode, $locationId);
    }

    public function priceForReference(
        User $user,
        ?string $referenceType,
        ?int $referenceId,
        ?string $label,
        ?int $foodItemId = null,
        ?string $storeBrand = null,
    ): ?float {
        $buildQuery = function (?string $brand) use ($user, $referenceType, $referenceId, $label, $foodItemId) {
            $query = BudgetEntry::where('user_id', $user->id);

            if ($brand !== null) {
                $query->where('store_brand', $brand);
            } else {
                $query->whereNull('store_brand');
            }

            if ($foodItemId) {
                $query->where('food_item_id', $foodItemId);
            } elseif ($referenceType && $referenceId) {
                $query->where('reference_type', $referenceType)->where('reference_id', $referenceId);
            } elseif ($label) {
                $query->where('label', $label);
            } else {
                return null;
            }

            return $query->value('price_per_kg');
        };

        if ($storeBrand !== null && trim($storeBrand) !== '') {
            $price = $buildQuery(trim($storeBrand));
            if ($price !== null) {
                return (float) $price;
            }
        }

        $price = $buildQuery(null);

        return $price !== null ? (float) $price : null;
    }

    public function preferredStoreBrand(User $user): ?string
    {
        $label = $user->profile?->open_prices_location_label;
        if (! $label) {
            return null;
        }

        if (str_contains($label, ' · ')) {
            return trim(explode(' · ', $label, 2)[0]);
        }

        $parts = preg_split('/\s+/', trim($label));

        return $parts[0] ?? null;
    }

    public function delete(User $user, int $entryId): void
    {
        BudgetEntry::where('user_id', $user->id)->where('id', $entryId)->delete();
    }

    public function entriesFor(User $user): Collection
    {
        return BudgetEntry::where('user_id', $user->id)->orderBy('label')->get();
    }

    public function hasAnyPrices(User $user): bool
    {
        return BudgetEntry::where('user_id', $user->id)->exists();
    }

    public function priceForIngredient(User $user, RecipeIngredient $ingredient): ?float
    {
        return $this->priceForReference(
            $user,
            $ingredient->reference_type?->value ?? $ingredient->reference_type,
            $ingredient->reference_id,
            $ingredient->label,
            $ingredient->food_item_id,
        );
    }

    public function calculateRecipeCost(User $user, Recipe $recipe, float $portions = 1): ?float
    {
        if ($recipe->is_macro_preset) {
            return null;
        }

        $recipe->loadMissing('ingredients');
        $total = 0.0;
        $hasPrice = false;

        foreach ($recipe->ingredients as $ingredient) {
            $pricePerKg = $this->priceForIngredient($user, $ingredient);
            if ($pricePerKg === null) {
                continue;
            }
            $hasPrice = true;
            $total += ($pricePerKg / 1000) * (float) $ingredient->quantity_g * ($portions / max($recipe->servings, 1));
        }

        return $hasPrice ? round($total, 2) : null;
    }

    public function calculateEntryCost(User $user, MealPlanEntry $entry): ?float
    {
        $entry->loadMissing(['recipe.ingredients', 'foodItem']);

        if ($entry->quantity_g !== null && ($entry->reference_type || $entry->food_item_id)) {
            return $this->calculateFoodLineCost($user, $entry);
        }

        if ($entry->recipe_id && $entry->recipe) {
            return $this->calculateRecipeCost($user, $entry->recipe, (float) ($entry->portions ?? 1));
        }

        return null;
    }

    private function calculateFoodLineCost(User $user, MealPlanEntry $entry): ?float
    {
        $pricePerKg = $this->priceForReference(
            $user,
            $entry->reference_type?->value,
            $entry->reference_id,
            $entry->label,
            $entry->food_item_id,
        );

        if ($pricePerKg === null) {
            return null;
        }

        return round(($pricePerKg / 1000) * (float) $entry->quantity_g, 2);
    }

    public function syncEntryCost(User $user, MealPlanEntry $entry): void
    {
        $cost = $this->calculateEntryCost($user, $entry);
        $entry->update(['estimated_cost' => $cost]);
    }

    public function weeklyTotal(User $user, ?Carbon $weekStart = null): array
    {
        $start = ($weekStart ?? now())->copy()->startOfWeek();
        $end = $start->copy()->addDays(6);

        $entries = MealPlanEntry::query()
            ->whereHas('mealPlan', fn ($q) => $q->where('user_id', $user->id))
            ->whereBetween('planned_on', [$start->toDateString(), $end->toDateString()])
            ->get();

        $spent = $entries->sum(fn ($e) => (float) ($e->estimated_cost ?? 0));
        $pricedEntries = $entries->filter(fn ($e) => $e->estimated_cost !== null)->count();
        $totalEntries = $entries->count();

        return [
            'spent' => round($spent, 2),
            'entry_count' => $totalEntries,
            'priced_count' => $pricedEntries,
            'has_prices' => $this->hasAnyPrices($user),
            'fully_priced' => $totalEntries > 0 && $pricedEntries === $totalEntries,
        ];
    }
}
