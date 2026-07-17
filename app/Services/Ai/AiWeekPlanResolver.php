<?php

namespace App\Services\Ai;

use App\Data\AiWeekPlanDraft;
use App\Data\AiWeekPlanItemDraft;
use App\Enums\FoodReferenceType;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Nutrition\FoodSearchService;
use App\Support\MealSlots;

class AiWeekPlanResolver
{
    public function __construct(
        private FoodSearchService $foodSearch,
    ) {}

    /**
     * @param  array{days: list<array{date: string, slots: array<string, list<array{label: string, quantity_g: ?float, recipe_id: ?int, recipe_hint: ?string}>}>}  $parsed
     */
    public function resolve(User $user, array $parsed): AiWeekPlanDraft
    {
        $recipes = $user->recipes()->get(['id', 'name']);
        $defaultQty = (float) config('futurmeal.ai.default_quantity_g', 150);
        $items = [];
        $errors = [];

        foreach ($parsed['days'] as $day) {
            $date = $day['date'];
            foreach (MealSlots::keys() as $slot) {
                foreach ($day['slots'][$slot] ?? [] as $rawItem) {
                    $items[] = $this->resolveItem($user, $recipes, $date, $slot, $rawItem, $defaultQty, $errors);
                }
            }
        }

        return new AiWeekPlanDraft($items, $errors);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Recipe>  $recipes
     * @param  array{label: string, quantity_g: ?float, recipe_id: ?int, recipe_hint: ?string}  $rawItem
     * @param  list<string>  $errors
     */
    private function resolveItem(
        User $user,
        $recipes,
        string $date,
        string $slot,
        array $rawItem,
        float $defaultQty,
        array &$errors,
    ): AiWeekPlanItemDraft {
        $label = $rawItem['label'];
        $quantityG = $rawItem['quantity_g'] ?? $defaultQty;

        $recipe = $this->matchRecipe($recipes, $rawItem['recipe_id'] ?? null, $rawItem['recipe_hint'] ?? null, $label);
        if ($recipe) {
            return new AiWeekPlanItemDraft(
                date: $date,
                slot: $slot,
                label: $recipe->name,
                quantityG: null,
                recipeId: $recipe->id,
                referenceType: null,
                referenceId: null,
                foodItemId: null,
                resolved: true,
                matchKind: 'recipe',
            );
        }

        $search = $this->foodSearch->search($label, $user, 5);
        $hit = $search['results'][0] ?? null;

        if ($hit && isset($hit['type'], $hit['id'], $hit['label'])) {
            $type = FoodReferenceType::tryFrom((string) $hit['type']);
            $foodItemId = $type && in_array($type, [FoodReferenceType::OpenFoodFacts, FoodReferenceType::Custom], true)
                ? (int) $hit['id']
                : null;

            return new AiWeekPlanItemDraft(
                date: $date,
                slot: $slot,
                label: (string) $hit['label'],
                quantityG: $quantityG,
                recipeId: null,
                referenceType: $type?->value,
                referenceId: (int) $hit['id'],
                foodItemId: $foodItemId,
                resolved: true,
                matchKind: 'food',
            );
        }

        $warning = "Aucun match pour « {$label} »";
        $errors[] = "{$date} / {$slot} : {$warning}";

        return new AiWeekPlanItemDraft(
            date: $date,
            slot: $slot,
            label: $label,
            quantityG: $quantityG,
            recipeId: null,
            referenceType: null,
            referenceId: null,
            foodItemId: null,
            resolved: false,
            warning: $warning,
            matchKind: 'none',
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Recipe>  $recipes
     */
    private function matchRecipe($recipes, ?int $recipeId, ?string $recipeHint, string $label): ?Recipe
    {
        if ($recipeId) {
            $byId = $recipes->firstWhere('id', $recipeId);
            if ($byId) {
                return $byId;
            }
        }

        $candidates = array_values(array_filter([$recipeHint, $label]));
        $best = null;
        $bestScore = 0.0;

        foreach ($candidates as $needle) {
            $needleNorm = $this->normalize($needle);
            if ($needleNorm === '') {
                continue;
            }

            foreach ($recipes as $recipe) {
                $hay = $this->normalize($recipe->name);
                if ($hay === $needleNorm || str_contains($hay, $needleNorm) || str_contains($needleNorm, $hay)) {
                    return $recipe;
                }

                similar_text($needleNorm, $hay, $percent);
                if ($percent > $bestScore) {
                    $bestScore = $percent;
                    $best = $recipe;
                }
            }
        }

        return $bestScore >= 72.0 ? $best : null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }
}
