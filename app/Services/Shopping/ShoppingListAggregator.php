<?php

namespace App\Services\Shopping;

use App\Models\MealPlanEntry;
use App\Models\User;
use Carbon\Carbon;

class ShoppingListAggregator
{
    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     quantity_g: float,
     *     reference_type: ?string,
     *     reference_id: ?int,
     *     food_item_id: ?int
     * }>
     */
    public function aggregate(User $user, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $entries = MealPlanEntry::query()
            ->whereHas('mealPlan', fn ($q) => $q->where('user_id', $user->id)->whereNull('program_id'))
            ->whereBetween('planned_on', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
            ->with(['recipe.ingredients', 'foodItem'])
            ->get();

        /** @var array<string, array{key: string, label: string, quantity_g: float, reference_type: ?string, reference_id: ?int, food_item_id: ?int}> $buckets */
        $buckets = [];

        foreach ($entries as $entry) {
            foreach ($this->linesFromEntry($entry) as $line) {
                $key = $line['key'];
                if (! isset($buckets[$key])) {
                    $buckets[$key] = $line;
                    continue;
                }

                $buckets[$key]['quantity_g'] += $line['quantity_g'];
                // Prefer a more specific label if current is empty-ish
                if (mb_strlen($line['label']) > mb_strlen($buckets[$key]['label'])) {
                    $buckets[$key]['label'] = $line['label'];
                }
            }
        }

        return collect($buckets)
            ->map(function (array $row) {
                $row['quantity_g'] = round($row['quantity_g'], 1);

                return $row;
            })
            ->sortBy(fn (array $row) => mb_strtolower($row['label']), SORT_NATURAL)
            ->values()
            ->all();
    }

    /**
     * @return list<array{key: string, label: string, quantity_g: float, reference_type: ?string, reference_id: ?int, food_item_id: ?int}>
     */
    private function linesFromEntry(MealPlanEntry $entry): array
    {
        if ($entry->quantity_g !== null && ($entry->reference_type || $entry->food_item_id || filled($entry->label))) {
            $label = $entry->label ?: ($entry->foodItem?->name ?? 'Aliment');

            return [[
                'key' => $this->aggregateKey(
                    $entry->food_item_id,
                    $entry->reference_type?->value,
                    $entry->reference_id,
                    $label,
                ),
                'label' => $label,
                'quantity_g' => (float) $entry->quantity_g,
                'reference_type' => $entry->reference_type?->value,
                'reference_id' => $entry->reference_id,
                'food_item_id' => $entry->food_item_id,
            ]];
        }

        $recipe = $entry->recipe;
        if (! $recipe || $recipe->is_macro_preset) {
            return [];
        }

        $recipe->loadMissing('ingredients');
        $servings = max(1, (float) ($recipe->servings ?: 1));
        $portions = (float) ($entry->portions ?? 1);
        $scale = $portions / $servings;

        $lines = [];
        foreach ($recipe->ingredients as $ingredient) {
            $label = $ingredient->label ?: ($ingredient->foodItem?->name ?? 'Ingrédient');
            $lines[] = [
                'key' => $this->aggregateKey(
                    $ingredient->food_item_id,
                    $ingredient->reference_type?->value,
                    $ingredient->reference_id,
                    $label,
                ),
                'label' => $label,
                'quantity_g' => (float) $ingredient->quantity_g * $scale,
                'reference_type' => $ingredient->reference_type?->value,
                'reference_id' => $ingredient->reference_id,
                'food_item_id' => $ingredient->food_item_id,
            ];
        }

        return $lines;
    }

    public function aggregateKey(
        ?int $foodItemId,
        ?string $referenceType,
        ?int $referenceId,
        string $label,
    ): string {
        if ($foodItemId) {
            return 'food:'.$foodItemId;
        }

        if ($referenceType && $referenceId) {
            return $referenceType.':'.$referenceId;
        }

        return 'label:'.mb_strtolower(trim($label));
    }
}
