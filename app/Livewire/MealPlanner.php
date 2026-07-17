<?php

namespace App\Livewire;

use App\Enums\GoalType;
use App\Models\MealPlanEntry;
use App\Models\Program;
use App\Models\User;
use App\Services\Nutrition\MealPlanEntryCalculator;
use App\Services\Nutrition\MealPlannerService;
use App\Services\Plan\PlanRangeService;
use App\Services\Plan\PlanViewContextService;
use App\Services\Program\ProgramPlanService;
use App\Services\Social\PlanShareService;
use App\Support\MealSlots;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class MealPlanner extends Component
{
    public string $weekStart;

    public ?int $mealPlanId = null;

    public ?int $programId = null;

    public ?int $viewUserId = null;

    public string $planContextKey = 'self';

    public bool $canEdit = true;

    public bool $showInvitePanel = false;

    public ?int $inviteFriendId = null;

    public bool $inviteCanEdit = false;

    public bool $showRangePanel = false;

    /** copy | clear */
    public string $rangeAction = 'clear';

    public string $rangeSourceStart = '';

    public string $rangeSourceEnd = '';

    public string $rangeTargetStart = '';

    public ?string $rangeError = null;

    public function mount(
        ProgramPlanService $programPlan,
        PlanViewContextService $planContext,
        MealPlannerService $planner,
    ): void {
        $this->weekStart = now()->startOfWeek()->toDateString();
        $this->programId = request()->integer('program') ?: null;
        $this->viewUserId = request()->integer('view') ?: null;
        $this->syncContextKey();
        $this->resolvePlan($programPlan, $planContext, $planner);
    }

    public function updatedPlanContextKey(
        ProgramPlanService $programPlan,
        PlanViewContextService $planContext,
        MealPlannerService $planner,
    ): void {
        if (str_starts_with($this->planContextKey, 'friend:')) {
            $this->viewUserId = (int) substr($this->planContextKey, 7);
            $this->programId = null;
            $planContext->setPrimary(Auth::user(), $this->viewUserId);
        } elseif (str_starts_with($this->planContextKey, 'program:')) {
            $this->programId = (int) substr($this->planContextKey, 9);
            $this->viewUserId = null;
            $planContext->setPrimary(Auth::user(), null);
        } else {
            $this->viewUserId = null;
            $this->programId = null;
            $planContext->setPrimary(Auth::user(), null);
        }

        $this->resolvePlan($programPlan, $planContext, $planner);
    }

    public function previousWeek(
        ProgramPlanService $programPlan,
        PlanViewContextService $planContext,
        MealPlannerService $planner,
    ): void {
        $step = $this->periodLength();
        $this->weekStart = Carbon::parse($this->weekStart)->subDays($step)->toDateString();
        $this->resolvePlan($programPlan, $planContext, $planner);
    }

    public function nextWeek(
        ProgramPlanService $programPlan,
        PlanViewContextService $planContext,
        MealPlannerService $planner,
    ): void {
        $step = $this->periodLength();
        $this->weekStart = Carbon::parse($this->weekStart)->addDays($step)->toDateString();
        $this->resolvePlan($programPlan, $planContext, $planner);
    }

    private function periodLength(): int
    {
        if ($this->programId) {
            return 7;
        }

        return Auth::user()->profile?->planning_horizon_days ?? 7;
    }

    public function toggleInvitePanel(): void
    {
        $this->showInvitePanel = ! $this->showInvitePanel;
        $this->inviteFriendId = null;
        $this->inviteCanEdit = false;
    }

    public function inviteFriend(PlanShareService $planShares): void
    {
        if (! $this->inviteFriendId) {
            return;
        }

        $friend = User::findOrFail($this->inviteFriendId);
        $planShares->inviteToFollow(Auth::user(), $friend, $this->inviteCanEdit);

        $this->showInvitePanel = false;
        session()->flash('planner-status', "Invitation envoyée à {$friend->name}.");
    }

    public function toggleFollowerCanEdit(int $shareId, bool $canEdit, PlanShareService $planShares): void
    {
        $planShares->updateCanEdit(Auth::user(), $shareId, $canEdit);
    }

    public function revokeFollower(int $shareId, PlanShareService $planShares): void
    {
        $planShares->revoke(Auth::user(), $shareId);
    }

    public function setProgramLock(int $programId, bool $locked): void
    {
        $program = Program::findOrFail($programId);
        if ($program->owner_id !== Auth::id()) {
            return;
        }

        $program->update(['lock_portions' => $locked]);
    }

    #[On('ai-week-applied')]
    public function onAiWeekApplied(): void
    {
        // Re-render pour recharger les entrées après application IA.
    }

    public function openRangePanel(string $action = 'clear'): void
    {
        if (! $this->canEdit) {
            return;
        }

        $this->rangeAction = in_array($action, ['copy', 'clear'], true) ? $action : 'clear';
        $this->rangeError = null;
        $start = Carbon::parse($this->weekStart)->toDateString();
        $end = Carbon::parse($this->weekStart)->addDays($this->periodLength() - 1)->toDateString();
        $this->rangeSourceStart = $start;
        $this->rangeSourceEnd = $end;
        $this->rangeTargetStart = Carbon::parse($end)->addDay()->toDateString();
        $this->showRangePanel = true;
        $this->showInvitePanel = false;
    }

    public function closeRangePanel(): void
    {
        $this->showRangePanel = false;
        $this->rangeError = null;
    }

    public function applyRangeAction(PlanRangeService $ranges): void
    {
        if (! $this->canEdit || ! $this->mealPlanId) {
            return;
        }

        $this->rangeError = null;

        try {
            if ($this->rangeAction === 'clear') {
                $this->validate([
                    'rangeSourceStart' => ['required', 'date'],
                    'rangeSourceEnd' => ['required', 'date', 'after_or_equal:rangeSourceStart'],
                ], [
                    'rangeSourceEnd.after_or_equal' => 'La date de fin doit être ≥ au début.',
                ]);

                $result = $ranges->clearRange(
                    Auth::user(),
                    $this->programId,
                    $this->mealPlanId,
                    $this->rangeSourceStart,
                    $this->rangeSourceEnd,
                );

                session()->flash(
                    'planner-status',
                    "Plage vidée : {$result['days']} jour(s), {$result['deleted']} entrée(s) supprimée(s).",
                );
            } else {
                $this->validate([
                    'rangeSourceStart' => ['required', 'date'],
                    'rangeSourceEnd' => ['required', 'date', 'after_or_equal:rangeSourceStart'],
                    'rangeTargetStart' => ['required', 'date'],
                ], [
                    'rangeSourceEnd.after_or_equal' => 'La date de fin source doit être ≥ au début.',
                ]);

                $result = $ranges->copyRange(
                    Auth::user(),
                    $this->programId,
                    $this->mealPlanId,
                    $this->rangeSourceStart,
                    $this->rangeSourceEnd,
                    $this->rangeTargetStart,
                );

                session()->flash(
                    'planner-status',
                    "Duplication : {$result['copied_days']} jour(s), {$result['created']} entrée(s) créée(s).",
                );
            }

            $this->closeRangePanel();
        } catch (ValidationException $e) {
            $this->rangeError = collect($e->validator->errors()->all())->first();
        } catch (InvalidArgumentException $e) {
            $this->rangeError = $e->getMessage();
        }
    }

    private function syncContextKey(): void
    {
        if ($this->programId) {
            $this->planContextKey = 'program:'.$this->programId;
        } elseif ($this->viewUserId) {
            $this->planContextKey = 'friend:'.$this->viewUserId;
        } else {
            $this->planContextKey = 'self';
        }
    }

    private function resolvePlan(
        ProgramPlanService $programPlan,
        PlanViewContextService $planContext,
        MealPlannerService $planner,
    ): void {
        $user = Auth::user();
        $context = $planContext->resolve($user, $this->viewUserId, $this->programId);
        $start = Carbon::parse($this->weekStart);

        if ($context->isProgram()) {
            $plan = $programPlan->resolvePlan($user, $this->programId, $start);
        } else {
            $plan = $planner->ensureDefaultPlan($context->planOwner);
        }

        $this->mealPlanId = $plan->id;
        $this->canEdit = $context->canEdit;
    }

    public function render(
        MealPlanEntryCalculator $entryCalculator,
        ProgramPlanService $programPlan,
        PlanShareService $planShares,
        PlanViewContextService $planContext,
        \App\Services\Budget\BudgetService $budget,
    ) {
        $user = Auth::user();
        $context = $planContext->resolve($user, $this->viewUserId, $this->programId);
        $start = Carbon::parse($this->weekStart);
        $horizon = $this->programId ? 7 : ($user->profile?->planning_horizon_days ?? 7);
        $days = collect(range(0, $horizon - 1))->map(fn ($i) => $start->copy()->addDays($i));
        $rangeEnd = $start->copy()->addDays($horizon - 1);

        $entries = MealPlanEntry::query()
            ->where('meal_plan_id', $this->mealPlanId)
            ->whereBetween('planned_on', [$start->toDateString(), $rangeEnd->toDateString()])
            ->with(['recipe', 'foodItem'])
            ->orderBy('sort_order')
            ->get();

        $entriesByDay = $entries->groupBy(fn ($e) => $e->planned_on->toDateString());

        $daySummaries = $days->mapWithKeys(function ($day) use ($entriesByDay, $entryCalculator) {
            $dayEntries = $entriesByDay->get($day->toDateString(), collect());
            $total = new \App\Data\NutrientProfile;
            $cost = 0.0;
            $hasCost = false;
            $bySlot = [];

            foreach ($dayEntries->groupBy(fn ($e) => MealSlots::normalize($e->meal_slot)) as $slotKey => $slotEntries) {
                $labels = [];
                foreach ($slotEntries as $entry) {
                    $total = $total->add($entryCalculator->calculate($entry));
                    if ($entry->estimated_cost !== null) {
                        $hasCost = true;
                        $cost += (float) $entry->estimated_cost;
                    }
                    $labels[] = $entryCalculator->label($entry);
                }
                $bySlot[$slotKey] = $labels;
            }

            return [$day->toDateString() => [
                'totals' => $total->toArray(),
                'cost' => $hasCost ? round($cost, 2) : null,
                'by_slot' => $bySlot,
                'entry_count' => $dayEntries->count(),
            ]];
        });

        $pricedCount = $entries->filter(fn ($e) => $e->estimated_cost !== null)->count();
        $weekCost = [
            'spent' => round($entries->sum(fn ($e) => (float) ($e->estimated_cost ?? 0)), 2),
            'entry_count' => $entries->count(),
            'priced_count' => $pricedCount,
            'has_prices' => $budget->hasAnyPrices($context->planOwner),
            'fully_priced' => $entries->count() > 0 && $pricedCount === $entries->count(),
        ];

        $weeklyDeficit = 0;
        $target = $user->profile?->daily_calorie_target ?? 2000;
        $goalType = $user->profile?->goal_type?->value ?? GoalType::WeightLoss->value;
        foreach ($daySummaries as $summary) {
            $weeklyDeficit += $target - (int) ($summary['totals']['energy_kcal'] ?? 0);
        }

        $projection = app(\App\Services\Body\BodyMetricCalculator::class)
            ->weeklyWeightProjection($weeklyDeficit, $goalType);

        $kcalChartLabels = $days->map(fn ($d) => $d->format('D d/m'))->values();
        $kcalChartData = $days->map(fn ($d) => (int) ($daySummaries[$d->toDateString()]['totals']['energy_kcal'] ?? 0))->values();
        $kcalChartDatasets = [
            [
                'type' => 'bar',
                'label' => 'Kcal planifiées',
                'data' => $kcalChartData,
                'backgroundColor' => 'rgba(0,255,136,0.35)',
                'borderColor' => '#00FF88',
                'borderWidth' => 1,
            ],
            [
                'type' => 'line',
                'label' => 'Objectif',
                'data' => array_fill(0, $horizon, $target),
                'borderColor' => '#8B95A5',
                'borderWidth' => 2,
                'pointRadius' => 0,
                'fill' => false,
            ],
        ];

        $activeProgram = $this->programId ? Program::with('members.user')->find($this->programId) : null;
        $programMembers = $activeProgram
            ? $activeProgram->members->map(fn ($m) => [
                'name' => $m->user->name,
                'role' => $m->role->value,
                'can_edit' => $m->role->value === 'owner' || ! $activeProgram->lock_portions,
                'share_metrics' => $m->share_metrics,
            ])
            : collect();

        $contextParams = fn (?int $view = null, ?int $program = null) => array_filter([
            'view' => $view ?? $this->viewUserId,
            'program' => $program ?? $this->programId,
        ]);

        return view('livewire.meal-planner', [
            'days' => $days,
            'daySummaries' => $daySummaries,
            'weekCost' => $weekCost,
            'slots' => MealSlots::ordered(),
            'calorieTarget' => $target,
            'planningHorizon' => $user->profile?->planning_horizon_days ?? 7,
            'contexts' => $planContext->availableContexts($user),
            'planContext' => $context,
            'followers' => $context->isSelf() ? $planShares->followersOf($user) : collect(),
            'friends' => app(\App\Services\Social\FriendshipService::class)->friendsOf($user),
            'activeProgram' => $activeProgram,
            'programMembers' => $programMembers,
            'projection' => $projection,
            'horizonDays' => $horizon,
            'kcalChartLabels' => $kcalChartLabels,
            'kcalChartDatasets' => $kcalChartDatasets,
            'contextParams' => $contextParams,
        ]);
    }
}
