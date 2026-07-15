<?php

namespace App\Livewire;

use App\Enums\GoalType;
use App\Models\MealPlanEntry;
use App\Models\Program;
use App\Models\User;
use App\Services\Nutrition\MealPlanEntryCalculator;
use App\Services\Nutrition\MealPlannerService;
use App\Services\Plan\PlanViewContextService;
use App\Services\Program\ProgramPlanService;
use App\Services\Social\PlanShareService;
use App\Support\MealSlots;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
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
    ) {
        $user = Auth::user();
        $context = $planContext->resolve($user, $this->viewUserId, $this->programId);
        $start = Carbon::parse($this->weekStart);
        $horizon = $this->programId ? 7 : ($user->profile?->planning_horizon_days ?? 7);
        $days = collect(range(0, $horizon - 1))->map(fn ($i) => $start->copy()->addDays($i));

        $entries = MealPlanEntry::query()
            ->where('meal_plan_id', $this->mealPlanId)
            ->whereBetween('planned_on', [$start->toDateString(), $start->copy()->addDays($horizon - 1)->toDateString()])
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
            'kcalChartData' => $kcalChartData,
            'calorieTargetLine' => array_fill(0, $horizon, $target),
            'contextParams' => $contextParams,
        ]);
    }
}
