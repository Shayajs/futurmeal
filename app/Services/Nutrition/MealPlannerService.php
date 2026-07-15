<?php

namespace App\Services\Nutrition;

use App\Data\NutrientProfile;
use App\Models\MealPlan;
use App\Models\MealPlanEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MealPlannerService
{
    public function __construct(private MealPlanEntryCalculator $entryCalculator) {}

    public function dailyNutrients(User $user, ?Carbon $date = null): NutrientProfile
    {
        return $this->dailyNutrientsForPlanOwner($user, $date);
    }

    public function dailyNutrientsForPlanOwner(User $owner, ?Carbon $date = null): NutrientProfile
    {
        $date ??= now()->startOfDay();

        $entries = MealPlanEntry::query()
            ->whereHas('mealPlan', fn ($q) => $q->where('user_id', $owner->id)->whereNull('program_id'))
            ->whereDate('planned_on', $date)
            ->with(['recipe.ingredients', 'foodItem'])
            ->get();

        return $this->sumEntries($entries);
    }

    public function weeklyNutrientsForPlanOwner(User $owner, ?Carbon $start = null): array
    {
        $start ??= now()->startOfWeek();
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $days[$day->toDateString()] = $this->dailyNutrientsForPlanOwner($owner, $day)->toArray();
        }

        return $days;
    }

    public function weeklyNutrients(User $user, ?Carbon $start = null): array
    {
        $start ??= now()->startOfWeek();
        $days = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $days[$day->toDateString()] = $this->dailyNutrients($user, $day)->toArray();
        }

        return $days;
    }

    public function sumEntries(Collection $entries): NutrientProfile
    {
        $total = new NutrientProfile;

        foreach ($entries as $entry) {
            $profile = $this->entryCalculator->calculate($entry);
            $total = $total->add($profile);
        }

        return $total;
    }

    public function ensureDefaultPlan(User $user): MealPlan
    {
        $horizon = $user->profile?->planning_horizon_days ?? 7;
        $start = now()->startOfDay();
        $end = $start->copy()->addDays($horizon - 1);

        return MealPlan::firstOrCreate(
            [
                'user_id' => $user->id,
                'name' => 'Plan '.$start->format('d/m/Y'),
            ],
            [
                'starts_on' => $start,
                'ends_on' => $end,
            ]
        );
    }
}
