<?php

namespace App\Services\Plan;

use App\Models\MealPlan;
use App\Models\MealPlanEntry;
use App\Models\User;
use App\Services\Budget\BudgetService;
use App\Services\Program\ProgramPlanService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PlanRangeService
{
    public const MAX_DAYS = 31;

    public function __construct(
        private ProgramPlanService $programPlan,
        private BudgetService $budget,
    ) {}

    /**
     * @return list<string>
     */
    public function datesBetween(string $from, string $to): array
    {
        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();

        if ($end->lt($start)) {
            throw new InvalidArgumentException('La date de fin doit être ≥ à la date de début.');
        }

        $days = $start->diffInDays($end) + 1;
        if ($days > self::MAX_DAYS) {
            throw new InvalidArgumentException('Plage limitée à '.self::MAX_DAYS.' jours.');
        }

        return collect(CarbonPeriod::create($start, $end))
            ->map(fn (Carbon $d) => $d->toDateString())
            ->values()
            ->all();
    }

    /**
     * @return array{deleted: int, days: int}
     */
    public function clearRange(
        User $user,
        ?int $programId,
        int $mealPlanId,
        string $from,
        string $to,
    ): array {
        $dates = $this->datesBetween($from, $to);
        $deleted = 0;

        DB::transaction(function () use ($user, $programId, $mealPlanId, $dates, &$deleted) {
            foreach ($dates as $date) {
                $plan = $this->planForDate($user, $programId, $mealPlanId, $date);
                $deleted += MealPlanEntry::query()
                    ->where('meal_plan_id', $plan->id)
                    ->whereDate('planned_on', $date)
                    ->delete();
            }
        });

        return ['deleted' => $deleted, 'days' => count($dates)];
    }

    /**
     * Duplique une plage source jour par jour vers une date de début cible.
     * Chaque jour cible est remplacé.
     *
     * @return array{copied_days: int, created: int}
     */
    public function copyRange(
        User $user,
        ?int $programId,
        int $mealPlanId,
        string $sourceFrom,
        string $sourceTo,
        string $targetStart,
    ): array {
        $sourceDates = $this->datesBetween($sourceFrom, $sourceTo);
        $targetStartDate = Carbon::parse($targetStart)->startOfDay();

        $copiedDays = 0;
        $created = 0;

        DB::transaction(function () use ($user, $programId, $mealPlanId, $sourceDates, $targetStartDate, &$copiedDays, &$created) {
            foreach ($sourceDates as $offset => $sourceDate) {
                $targetDate = $targetStartDate->copy()->addDays($offset)->toDateString();
                if ($targetDate === $sourceDate) {
                    continue;
                }

                $sourcePlan = $this->planForDate($user, $programId, $mealPlanId, $sourceDate);
                $targetPlan = $this->planForDate($user, $programId, $mealPlanId, $targetDate);

                $sourceEntries = MealPlanEntry::query()
                    ->where('meal_plan_id', $sourcePlan->id)
                    ->whereDate('planned_on', $sourceDate)
                    ->orderBy('sort_order')
                    ->get();

                if ($sourceEntries->isEmpty()) {
                    // Jour source vide : on vide quand même la cible pour rester cohérent
                    MealPlanEntry::query()
                        ->where('meal_plan_id', $targetPlan->id)
                        ->whereDate('planned_on', $targetDate)
                        ->delete();
                    $copiedDays++;

                    continue;
                }

                MealPlanEntry::query()
                    ->where('meal_plan_id', $targetPlan->id)
                    ->whereDate('planned_on', $targetDate)
                    ->delete();

                foreach ($sourceEntries as $source) {
                    $entry = MealPlanEntry::create([
                        'meal_plan_id' => $targetPlan->id,
                        'planned_on' => $targetDate,
                        'meal_slot' => $source->meal_slot,
                        'recipe_id' => $source->recipe_id,
                        'reference_type' => $source->reference_type,
                        'reference_id' => $source->reference_id,
                        'food_item_id' => $source->food_item_id,
                        'label' => $source->label,
                        'quantity_g' => $source->quantity_g,
                        'portions' => $source->portions,
                        'sort_order' => $source->sort_order,
                    ]);
                    $this->budget->syncEntryCost($user, $entry);
                    $created++;
                }

                $copiedDays++;
            }
        });

        return ['copied_days' => $copiedDays, 'created' => $created];
    }

    private function planForDate(User $user, ?int $programId, int $mealPlanId, string $date): MealPlan
    {
        if ($programId) {
            return $this->programPlan->resolvePlan($user, $programId, Carbon::parse($date));
        }

        return MealPlan::query()->findOrFail($mealPlanId);
    }
}
