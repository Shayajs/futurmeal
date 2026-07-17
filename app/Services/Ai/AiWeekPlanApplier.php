<?php

namespace App\Services\Ai;

use App\Data\AiWeekPlanDraft;
use App\Data\AiWeekPlanItemDraft;
use App\Enums\FoodReferenceType;
use App\Models\MealPlanEntry;
use App\Models\Recipe;
use App\Models\User;
use App\Services\Budget\BudgetService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AiWeekPlanApplier
{
    public function __construct(
        private BudgetService $budget,
    ) {}

    /**
     * Remplace les entrées de la période par le draft résolu.
     * Les items non résolus sont ignorés.
     *
     * @return array{created: int, skipped: int}
     */
    public function apply(
        User $user,
        int $mealPlanId,
        string $weekStart,
        int $horizonDays,
        AiWeekPlanDraft $draft,
    ): array {
        $rangeStart = Carbon::parse($weekStart)->startOfDay();
        $rangeDates = collect(range(0, max(1, $horizonDays) - 1))
            ->map(fn (int $i) => $rangeStart->copy()->addDays($i)->toDateString());

        $draftDates = collect($draft->items)
            ->map(fn (AiWeekPlanItemDraft $item) => $item->date)
            ->filter()
            ->unique();

        // Plage demandée + jours additionnels renvoyés par l'IA
        $clearDates = $rangeDates->merge($draftDates)->unique()->values()->all();

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($user, $mealPlanId, $clearDates, $draft, &$created, &$skipped) {
            $delete = MealPlanEntry::query()->where('meal_plan_id', $mealPlanId);
            $delete->where(function ($query) use ($clearDates) {
                foreach ($clearDates as $date) {
                    $query->orWhereDate('planned_on', $date);
                }
            })->delete();

            $sort = 0;
            foreach ($draft->items as $item) {
                if (! $item->resolved) {
                    $skipped++;

                    continue;
                }

                if ($item->recipeId) {
                    $created += $this->applyRecipe($user, $mealPlanId, $item, $sort);
                } else {
                    $this->applyFood($user, $mealPlanId, $item, $sort);
                    $created++;
                }
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    private function applyRecipe(User $user, int $mealPlanId, AiWeekPlanItemDraft $item, int &$sort): int
    {
        $recipe = Recipe::query()
            ->where('user_id', $user->id)
            ->with('ingredients')
            ->find($item->recipeId);

        if (! $recipe) {
            return 0;
        }

        $count = 0;

        if ($recipe->is_macro_preset) {
            $entry = MealPlanEntry::create([
                'meal_plan_id' => $mealPlanId,
                'planned_on' => $item->date,
                'meal_slot' => $item->slot,
                'recipe_id' => $recipe->id,
                'label' => $recipe->name,
                'portions' => 1,
                'sort_order' => $sort++,
            ]);
            $this->budget->syncEntryCost($user, $entry);
            $count++;

            return $count;
        }

        foreach ($recipe->ingredients as $ingredient) {
            $entry = MealPlanEntry::create([
                'meal_plan_id' => $mealPlanId,
                'planned_on' => $item->date,
                'meal_slot' => $item->slot,
                'recipe_id' => $recipe->id,
                'reference_type' => $ingredient->reference_type,
                'reference_id' => $ingredient->reference_id,
                'food_item_id' => $ingredient->food_item_id,
                'label' => $ingredient->label,
                'quantity_g' => (float) $ingredient->quantity_g,
                'sort_order' => $sort++,
            ]);
            $this->budget->syncEntryCost($user, $entry);
            $count++;
        }

        // Recette sans ingrédients : au moins une ligne label
        if ($count === 0) {
            $entry = MealPlanEntry::create([
                'meal_plan_id' => $mealPlanId,
                'planned_on' => $item->date,
                'meal_slot' => $item->slot,
                'recipe_id' => $recipe->id,
                'label' => $recipe->name,
                'portions' => 1,
                'sort_order' => $sort++,
            ]);
            $this->budget->syncEntryCost($user, $entry);
            $count++;
        }

        return $count;
    }

    private function applyFood(User $user, int $mealPlanId, AiWeekPlanItemDraft $item, int &$sort): void
    {
        $referenceType = FoodReferenceType::tryFrom($item->referenceType ?? '');

        $entry = MealPlanEntry::create([
            'meal_plan_id' => $mealPlanId,
            'planned_on' => $item->date,
            'meal_slot' => $item->slot,
            'reference_type' => $referenceType,
            'reference_id' => $item->referenceId,
            'food_item_id' => $item->foodItemId,
            'label' => $item->label,
            'quantity_g' => $item->quantityG ?? (float) config('futurmeal.ai.default_quantity_g', 150),
            'sort_order' => $sort++,
        ]);

        $this->budget->syncEntryCost($user, $entry);
    }
}
