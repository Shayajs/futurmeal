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
                $buckets[$key] = $this->preferRicherMeta($buckets[$key], $line);
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
                'label' => $this->displayLabel($label),
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
                'label' => $this->displayLabel($label),
                'quantity_g' => (float) $ingredient->quantity_g * $scale,
                'reference_type' => $ingredient->reference_type?->value,
                'reference_id' => $ingredient->reference_id,
                'food_item_id' => $ingredient->food_item_id,
            ];
        }

        return $lines;
    }

    /**
     * Fusionne par libellé normalisé pour éviter les doublons
     * (ex. plusieurs food_item_id « Yaourt nature » / « Whey isolat »).
     */
    public function aggregateKey(
        ?int $foodItemId,
        ?string $referenceType,
        ?int $referenceId,
        string $label,
    ): string {
        return 'label:'.$this->normalizeLabel($label);
    }

    public function normalizeLabel(string $label): string
    {
        $label = preg_replace('/\s*[·•]\s*perso\s*$/iu', '', $label) ?? $label;
        $label = trim(preg_replace('/\s+/u', ' ', $label) ?? $label);

        // Fold accents for merge (Yaourt / Yaourt…)
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        if (is_string($ascii) && $ascii !== '') {
            $label = $ascii;
        }

        return mb_strtolower($label);
    }

    private function displayLabel(string $label): string
    {
        $label = preg_replace('/\s*[·•]\s*perso\s*$/iu', '', $label) ?? $label;

        return trim(preg_replace('/\s+/u', ' ', $label) ?? $label);
    }

    /**
     * @param  array{key: string, label: string, quantity_g: float, reference_type: ?string, reference_id: ?int, food_item_id: ?int}  $current
     * @param  array{key: string, label: string, quantity_g: float, reference_type: ?string, reference_id: ?int, food_item_id: ?int}  $incoming
     * @return array{key: string, label: string, quantity_g: float, reference_type: ?string, reference_id: ?int, food_item_id: ?int}
     */
    private function preferRicherMeta(array $current, array $incoming): array
    {
        if (mb_strlen($incoming['label']) > mb_strlen($current['label'])) {
            $current['label'] = $incoming['label'];
        }

        // Prefer a catalogue reference when available
        if ($current['food_item_id'] === null && $incoming['food_item_id'] !== null) {
            $current['food_item_id'] = $incoming['food_item_id'];
        }
        if ($current['reference_type'] === null && $incoming['reference_type'] !== null) {
            $current['reference_type'] = $incoming['reference_type'];
            $current['reference_id'] = $incoming['reference_id'];
        }

        return $current;
    }
}
