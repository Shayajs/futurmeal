<?php

namespace App\Services\Social;

use App\Models\MealPlanEntry;
use App\Models\PublishedMenu;
use App\Models\User;
use App\Services\Budget\BudgetService;
use App\Services\Nutrition\MealPlanEntryCalculator;
use App\Support\MealSlots;
use Carbon\Carbon;

class PublishedMenuService
{
    public function __construct(
        private MealPlanEntryCalculator $entryCalculator,
        private BudgetService $budget,
    ) {}

    /** Fige la journée d'un plan en snapshot publiable. */
    public function publishDay(
        User $user,
        int $mealPlanId,
        string $date,
        string $title,
        ?string $description = null,
        bool $isPublic = true,
    ): ?PublishedMenu {
        $entries = MealPlanEntry::where('meal_plan_id', $mealPlanId)
            ->whereDate('planned_on', $date)
            ->with(['recipe', 'foodItem'])
            ->orderBy('sort_order')
            ->get();

        if ($entries->isEmpty()) {
            return null;
        }

        $snapshot = [];

        foreach ($entries as $entry) {
            $slot = MealSlots::normalize($entry->meal_slot);
            $nutrients = $this->entryCalculator->calculate($entry);

            $snapshot[$slot][] = [
                'reference_type' => $entry->reference_type?->value,
                'reference_id' => $entry->reference_id,
                'food_item_id' => $entry->food_item_id,
                'recipe_id' => null,
                'label' => $this->entryCalculator->label($entry),
                'quantity_g' => $entry->quantity_g !== null ? (float) $entry->quantity_g : null,
                'portions' => $entry->portions !== null ? (float) $entry->portions : null,
                'kcal' => (int) $nutrients->energyKcal,
            ];
        }

        return PublishedMenu::create([
            'user_id' => $user->id,
            'title' => $title,
            'description' => $description,
            'day_snapshot' => $snapshot,
            'is_public' => $isPublic,
        ]);
    }

    /** Applique un menu publié à une journée du plan de l'utilisateur (remplace la journée). */
    public function applyToDay(User $user, PublishedMenu $menu, int $mealPlanId, string $date): int
    {
        MealPlanEntry::where('meal_plan_id', $mealPlanId)
            ->whereDate('planned_on', $date)
            ->delete();

        $created = 0;
        $sort = 0;

        foreach ($menu->day_snapshot as $slot => $items) {
            if (! MealSlots::isValid($slot)) {
                continue;
            }

            foreach ($items as $item) {
                $entry = MealPlanEntry::create([
                    'meal_plan_id' => $mealPlanId,
                    'planned_on' => Carbon::parse($date)->toDateString(),
                    'meal_slot' => $slot,
                    'reference_type' => $item['reference_type'] ?? null,
                    'reference_id' => $item['reference_id'] ?? null,
                    'food_item_id' => $item['food_item_id'] ?? null,
                    'label' => $item['label'] ?? null,
                    'quantity_g' => $item['quantity_g'] ?? null,
                    'portions' => $item['portions'] ?? null,
                    'sort_order' => $sort++,
                ]);

                $this->budget->syncEntryCost($user, $entry);
                $created++;
            }
        }

        $menu->increment('copies_count');

        return $created;
    }
}
