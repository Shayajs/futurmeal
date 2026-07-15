<?php

namespace App\Services\Plan;

use App\Data\PlanViewContext;
use App\Models\User;
use App\Services\Program\ProgramPlanService;
use App\Services\Social\PlanShareService;
use Illuminate\Support\Collection;

class PlanViewContextService
{
    public function __construct(
        private PlanShareService $planShares,
        private ProgramPlanService $programPlan,
    ) {}

    public function resolve(User $viewer, ?int $viewUserId = null, ?int $programId = null): PlanViewContext
    {
        if ($programId) {
            $program = \App\Models\Program::find($programId);
            if ($program && $this->programPlan->membership($viewer, $programId)) {
                return new PlanViewContext(
                    type: 'program',
                    viewer: $viewer,
                    planOwner: $program->owner,
                    programId: $programId,
                    canEdit: $this->programPlan->canEditPlan($viewer, $program),
                );
            }
        }

        $friendId = $viewUserId ?? $viewer->profile?->plan_view_user_id;

        if ($friendId && $friendId !== $viewer->id) {
            $owner = User::find($friendId);
            if ($owner && $this->planShares->canViewPlan($viewer, $owner)) {
                $share = $this->planShares->shareBetween($owner, $viewer);

                return new PlanViewContext(
                    type: 'friend',
                    viewer: $viewer,
                    planOwner: $owner,
                    planShareId: $share?->id,
                    canEdit: $this->planShares->canEditPlan($viewer, $owner),
                );
            }
        }

        return new PlanViewContext(
            type: 'self',
            viewer: $viewer,
            planOwner: $viewer,
            canEdit: true,
        );
    }

    public function setPrimary(User $viewer, ?int $friendUserId): void
    {
        if (! $viewer->profile) {
            return;
        }

        if ($friendUserId === null || $friendUserId === $viewer->id) {
            $viewer->profile->update(['plan_view_user_id' => null]);

            return;
        }

        $owner = User::find($friendUserId);
        if ($owner && $this->planShares->canViewPlan($viewer, $owner)) {
            $viewer->profile->update(['plan_view_user_id' => $friendUserId]);
        }
    }

    /** @return array{self: string, friends: Collection, programs: Collection} */
    public function availableContexts(User $viewer): array
    {
        return [
            'self' => 'Mon plan',
            'friends' => $this->planShares->followingFor($viewer),
            'programs' => $this->programPlan->programsFor($viewer),
        ];
    }

    public function mealPlanOwner(PlanViewContext $context): User
    {
        return $context->planOwner;
    }
}
