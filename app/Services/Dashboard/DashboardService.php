<?php

namespace App\Services\Dashboard;

use App\Models\MealPlanEntry;
use App\Models\User;
use App\Support\MealSlots;
use App\Services\Body\BodyMetricCalculator;
use App\Services\Budget\BudgetService;
use App\Services\Nutrition\MealPlanEntryCalculator;
use App\Services\Nutrition\MealPlannerService;
use App\Services\Plan\PlanViewContextService;
use App\Services\Program\ProgramPlanService;
use Carbon\Carbon;

class DashboardService
{
    public function __construct(
        private MealPlannerService $planner,
        private MealPlanEntryCalculator $entryCalculator,
        private BodyMetricCalculator $bodyCalculator,
        private BudgetService $budget,
        private ProgramPlanService $programPlan,
        private PlanViewContextService $planContext,
    ) {}

    public function build(User $user): array
    {
        $profile = $user->profile;
        $context = $this->planContext->resolve($user);
        $mealOwner = $context->planOwner;

        $today = $this->planner->dailyNutrientsForPlanOwner($mealOwner);
        $target = $profile?->daily_calorie_target ?? 2000;
        $consumed = (int) $today->energyKcal;
        $remaining = max(0, $target - $consumed);

        $latestMetric = $user->bodyMetrics()->orderByDesc('recorded_at')->first();
        $startingMetric = $user->bodyMetrics()->orderBy('recorded_at')->first();
        $metrics30 = $user->bodyMetrics()->orderByDesc('recorded_at')->limit(30)->get()->reverse()->values();

        $weekly = $this->planner->weeklyNutrientsForPlanOwner($mealOwner);
        $weeklyBudget = $this->budget->weeklyTotal($user);

        $deficit = $target - $consumed;
        $weeklyDeficit = $this->weeklyDeficitFromWeekly($weekly, $target);

        return [
            'plan_context' => [
                'type' => $context->type,
                'label' => $context->label(),
                'owner_name' => $mealOwner->name,
                'is_supervising' => $context->isFriend(),
            ],
            'greeting' => [
                'name' => $user->name,
                'goal_label' => $profile?->goal_type?->label(),
                'date' => now()->translatedFormat('l j F'),
            ],
            'today' => [
                'target' => $target,
                'consumed' => $consumed,
                'remaining' => $remaining,
                'deficit' => $deficit,
                'macros' => $today->toArray(),
            ],
            'today_meals' => $this->todayMeals($mealOwner),
            'weekly_calories' => [
                'labels' => array_map(fn ($d) => Carbon::parse($d)->translatedFormat('D'), array_keys($weekly)),
                'data' => array_column($weekly, 'energy_kcal'),
                'target' => $target,
            ],
            'weekly_budget' => $weeklyBudget,
            'body' => [
                'latest_weight' => $latestMetric?->weight_kg,
                'latest_body_fat' => $latestMetric?->body_fat_percent,
                'latest_bmi' => $latestMetric?->bmi,
                'target_weight' => $profile?->target_weight_kg,
                'target_body_fat' => $profile?->target_body_fat_percent,
                'weight_progress' => $profile?->weightGoalProgress(
                    $latestMetric?->weight_kg !== null ? (float) $latestMetric->weight_kg : null,
                    $startingMetric?->weight_kg !== null ? (float) $startingMetric->weight_kg : null,
                ),
                'body_fat_progress' => $profile?->bodyFatGoalProgress(
                    $latestMetric?->body_fat_percent !== null ? (float) $latestMetric->body_fat_percent : null,
                    $startingMetric?->body_fat_percent !== null ? (float) $startingMetric->body_fat_percent : null,
                ),
                'weight_delta' => $this->weightDelta($metrics30),
                'chart' => [
                    'labels' => $metrics30->map(fn ($m) => $m->recorded_at->format('d/m'))->values()->all(),
                    'weights' => $metrics30->pluck('weight_kg')->values()->all(),
                    'body_fat' => $metrics30->pluck('body_fat_percent')->values()->all(),
                ],
            ],
            'projection' => $this->weightProjection($weeklyDeficit),
            'programs' => $this->programContext($user),
        ];
    }

    private function todayMeals(User $owner): array
    {
        $entries = MealPlanEntry::query()
            ->whereHas('mealPlan', fn ($q) => $q->where('user_id', $owner->id)->whereNull('program_id'))
            ->whereDate('planned_on', today())
            ->with(['recipe', 'foodItem'])
            ->get()
            ->groupBy(fn ($e) => MealSlots::normalize($e->meal_slot));

        return collect(MealSlots::ordered())->map(function ($label, $slotKey) use ($entries) {
            $slotEntries = $entries->get($slotKey, collect());

            if ($slotEntries->isEmpty()) {
                return [
                    'slot' => $label,
                    'slot_key' => $slotKey,
                    'foods' => [],
                    'name' => null,
                    'kcal' => null,
                    'protein_g' => null,
                    'carbs_g' => null,
                    'fat_g' => null,
                    'cost' => null,
                    'empty' => true,
                ];
            }

            $total = new \App\Data\NutrientProfile;
            $cost = 0.0;
            $hasCost = false;
            $foods = [];

            foreach ($slotEntries as $entry) {
                $nutrients = $this->entryCalculator->calculate($entry);
                $total = $total->add($nutrients);
                if ($entry->estimated_cost !== null) {
                    $hasCost = true;
                    $cost += (float) $entry->estimated_cost;
                }
                $foods[] = [
                    'label' => $this->entryCalculator->displayLine($entry),
                    'kcal' => (int) $nutrients->energyKcal,
                ];
            }

            return [
                'slot' => $label,
                'slot_key' => $slotKey,
                'foods' => $foods,
                'name' => collect($foods)->pluck('label')->implode(', '),
                'kcal' => (int) $total->energyKcal,
                'protein_g' => round($total->proteinG, 1),
                'carbs_g' => round($total->carbsG, 1),
                'fat_g' => round($total->fatG, 1),
                'cost' => $hasCost ? round($cost, 2) : null,
                'empty' => false,
            ];
        })->values()->all();
    }

    private function weeklyDeficitFromWeekly(array $weekly, int $target): float
    {
        $deficit = 0;

        foreach ($weekly as $day) {
            $deficit += $target - (int) ($day['energy_kcal'] ?? 0);
        }

        return $deficit;
    }

    private function weeklyDeficit(User $user, int $target): float
    {
        $weekly = $this->planner->weeklyNutrients($user);

        return $this->weeklyDeficitFromWeekly($weekly, $target);
    }

    private function weightProjection(float $weeklyDeficitKcal): array
    {
        $kg = $this->bodyCalculator->weightLossProjectionKg(max(0, $weeklyDeficitKcal));

        return [
            'weekly_deficit_kcal' => (int) $weeklyDeficitKcal,
            'estimated_kg' => $kg,
        ];
    }

    private function weightDelta($metrics): ?float
    {
        if ($metrics->count() < 2) {
            return null;
        }

        $first = (float) $metrics->first()->weight_kg;
        $last = (float) $metrics->last()->weight_kg;

        return round($last - $first, 1);
    }

    private function programContext(User $user): array
    {
        $memberships = $user->programMemberships()->with(['program.members.user', 'program.owner'])->get();

        return $memberships->map(function ($membership) {
            $program = $membership->program;

            return [
                'id' => $program->id,
                'name' => $program->name,
                'invite_code' => $program->invite_code,
                'is_owner' => $membership->role->value === 'owner',
                'lock_portions' => $program->lock_portions,
                'member_count' => $program->members->count(),
                'adherence' => $this->programPlan->adherenceRate($membership->user, $program),
                'shared_metrics' => $this->programPlan->sharedMemberMetrics($program)->all(),
                'members' => $program->members->map(fn ($m) => [
                    'name' => $m->user->name,
                    'share_metrics' => $m->share_metrics,
                ])->all(),
            ];
        })->all();
    }
}
