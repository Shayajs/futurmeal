<?php

namespace App\Services\Program;

use App\Enums\ProgramMemberRole;
use App\Models\MealPlan;
use App\Models\MealPlanEntry;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\User;
use App\Services\Nutrition\MealPlanEntryCalculator;
use App\Services\Nutrition\MealPlannerService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProgramPlanService
{
    public function __construct(
        private MealPlannerService $planner,
        private MealPlanEntryCalculator $entryCalculator,
    ) {}

    public function membership(User $user, int $programId): ?ProgramMember
    {
        return ProgramMember::where('program_id', $programId)
            ->where('user_id', $user->id)
            ->first();
    }

    public function canEditPlan(User $user, Program $program): bool
    {
        $member = $this->membership($user, $program->id);

        if (! $member) {
            return false;
        }

        if ($member->role === ProgramMemberRole::Owner) {
            return true;
        }

        return ! $program->lock_portions;
    }

    public function ensureProgramPlan(Program $program, User $owner, ?Carbon $weekStart = null): MealPlan
    {
        $start = ($weekStart ?? now())->copy()->startOfWeek();
        $end = $start->copy()->addDays(6);

        return MealPlan::firstOrCreate(
            [
                'program_id' => $program->id,
                'starts_on' => $start->toDateString(),
            ],
            [
                'user_id' => $owner->id,
                'name' => $program->name.' — semaine '.$start->format('d/m'),
                'ends_on' => $end->toDateString(),
            ]
        );
    }

    public function resolvePlan(User $user, ?int $programId = null, ?Carbon $weekStart = null): MealPlan
    {
        if ($programId) {
            $program = Program::findOrFail($programId);
            $member = $this->membership($user, $programId);
            abort_unless($member, 403);

            return $this->ensureProgramPlan($program, $program->owner, $weekStart);
        }

        return $this->planner->ensureDefaultPlan($user);
    }

    public function programsFor(User $user): Collection
    {
        return $user->programMemberships()
            ->with('program.owner')
            ->get()
            ->map(fn (ProgramMember $m) => $m->program);
    }

    public function adherenceRate(User $user, Program $program, int $days = 7): ?int
    {
        $target = $user->profile?->daily_calorie_target;
        if (! $target) {
            return null;
        }

        $plan = $this->ensureProgramPlan($program, $program->owner);
        $start = now()->startOfWeek();
        $threshold = $target * 0.8;
        $daysMet = 0;
        $daysChecked = 0;

        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            if ($day->isFuture()) {
                continue;
            }

            $entries = MealPlanEntry::where('meal_plan_id', $plan->id)
                ->whereDate('planned_on', $day)
                ->with(['recipe.ingredients', 'foodItem'])
                ->get();

            if ($entries->isEmpty()) {
                continue;
            }

            $daysChecked++;
            $kcal = 0;
            foreach ($entries as $entry) {
                $kcal += $this->entryCalculator->calculate($entry)->energyKcal;
            }

            if ($kcal >= $threshold) {
                $daysMet++;
            }
        }

        if ($daysChecked === 0) {
            return null;
        }

        return (int) round(($daysMet / $daysChecked) * 100);
    }

    public function sharedMemberMetrics(Program $program): Collection
    {
        return $program->members()
            ->with(['user.bodyMetrics' => fn ($q) => $q->limit(1)])
            ->where('share_metrics', true)
            ->get()
            ->map(function (ProgramMember $member) {
                $metric = $member->user->bodyMetrics()->first();

                return [
                    'name' => $member->user->name,
                    'weight_kg' => $metric?->weight_kg,
                    'body_fat_percent' => $metric?->body_fat_percent,
                    'role' => $member->role->value,
                ];
            });
    }

    public function memberAdherenceRates(Program $program, int $days = 7): Collection
    {
        return $program->members()
            ->with('user')
            ->get()
            ->map(fn (ProgramMember $member) => [
                'user_id' => $member->user_id,
                'name' => $member->user->name,
                'role' => $member->role->value,
                'adherence' => $this->adherenceRate($member->user, $program, $days),
            ]);
    }
}
