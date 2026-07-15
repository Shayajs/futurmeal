<?php

namespace App\Services\Social;

use App\Enums\PlanShareDirection;
use App\Enums\PlanShareStatus;
use App\Models\PlanShare;
use App\Models\User;
use App\Notifications\PlanFollowInviteNotification;
use App\Notifications\PlanFollowRequestNotification;
use App\Notifications\PlanShareAcceptedNotification;
use Illuminate\Support\Collection;

class PlanShareService
{
    public function __construct(private FriendshipService $friendships) {}

    public function requestToFollow(User $viewer, User $owner): ?PlanShare
    {
        if ($viewer->id === $owner->id || ! $this->friendships->areFriends($viewer, $owner)) {
            return null;
        }

        $existing = $this->shareBetween($owner, $viewer);
        if ($existing && in_array($existing->status, [PlanShareStatus::Pending, PlanShareStatus::Accepted], true)) {
            return null;
        }

        if ($existing) {
            $existing->update([
                'initiated_by' => $viewer->id,
                'direction' => PlanShareDirection::ViewerRequests,
                'status' => PlanShareStatus::Pending,
                'can_edit' => false,
            ]);
            $share = $existing->fresh();
        } else {
            $share = PlanShare::create([
                'owner_id' => $owner->id,
                'viewer_id' => $viewer->id,
                'initiated_by' => $viewer->id,
                'direction' => PlanShareDirection::ViewerRequests,
                'status' => PlanShareStatus::Pending,
                'can_edit' => false,
            ]);
        }

        $owner->notify(new PlanFollowRequestNotification($share->load(['viewer', 'owner'])));

        return $share;
    }

    public function inviteToFollow(User $owner, User $viewer, bool $canEdit = false): ?PlanShare
    {
        if ($owner->id === $viewer->id || ! $this->friendships->areFriends($owner, $viewer)) {
            return null;
        }

        $existing = $this->shareBetween($owner, $viewer);
        if ($existing && in_array($existing->status, [PlanShareStatus::Pending, PlanShareStatus::Accepted], true)) {
            return null;
        }

        if ($existing) {
            $existing->update([
                'initiated_by' => $owner->id,
                'direction' => PlanShareDirection::OwnerInvites,
                'status' => PlanShareStatus::Pending,
                'can_edit' => $canEdit,
            ]);
            $share = $existing->fresh();
        } else {
            $share = PlanShare::create([
                'owner_id' => $owner->id,
                'viewer_id' => $viewer->id,
                'initiated_by' => $owner->id,
                'direction' => PlanShareDirection::OwnerInvites,
                'status' => PlanShareStatus::Pending,
                'can_edit' => $canEdit,
            ]);
        }

        $viewer->notify(new PlanFollowInviteNotification($share->load(['viewer', 'owner'])));

        return $share;
    }

    public function accept(User $user, int $shareId, ?bool $canEdit = null): void
    {
        $share = PlanShare::findOrFail($shareId);

        if ($share->direction === PlanShareDirection::ViewerRequests && $share->owner_id !== $user->id) {
            return;
        }
        if ($share->direction === PlanShareDirection::OwnerInvites && $share->viewer_id !== $user->id) {
            return;
        }
        if ($share->status !== PlanShareStatus::Pending) {
            return;
        }

        $updates = ['status' => PlanShareStatus::Accepted];
        if ($canEdit !== null && $share->direction === PlanShareDirection::ViewerRequests) {
            $updates['can_edit'] = $canEdit;
        }

        $share->update($updates);
        $share = $share->fresh();

        $share = $share->fresh(['viewer', 'owner']);
        $share->viewer->notify(new PlanShareAcceptedNotification($share));
        $share->owner->notify(new PlanShareAcceptedNotification($share));
    }

    public function reject(User $user, int $shareId): void
    {
        $share = PlanShare::findOrFail($shareId);

        if (! $this->canRespond($user, $share)) {
            return;
        }

        $share->update(['status' => PlanShareStatus::Rejected]);
    }

    public function revoke(User $user, int $shareId): void
    {
        $share = PlanShare::findOrFail($shareId);

        if ($share->owner_id !== $user->id && $share->viewer_id !== $user->id) {
            return;
        }

        $share->update(['status' => PlanShareStatus::Revoked]);

        if ($user->profile?->plan_view_user_id === $share->owner_id && $user->id === $share->viewer_id) {
            $user->profile->update(['plan_view_user_id' => null]);
        }
    }

    public function updateCanEdit(User $owner, int $shareId, bool $canEdit): void
    {
        PlanShare::where('id', $shareId)
            ->where('owner_id', $owner->id)
            ->where('status', PlanShareStatus::Accepted)
            ->update(['can_edit' => $canEdit]);
    }

    public function canViewPlan(User $viewer, User $owner): bool
    {
        if ($viewer->id === $owner->id) {
            return true;
        }

        return $this->shareBetween($owner, $viewer)?->status === PlanShareStatus::Accepted;
    }

    public function canEditPlan(User $viewer, User $owner): bool
    {
        if ($viewer->id === $owner->id) {
            return true;
        }

        $share = $this->shareBetween($owner, $viewer);

        return $share?->status === PlanShareStatus::Accepted && $share->can_edit;
    }

    /** @return Collection<int, PlanShare> */
    public function followersOf(User $owner): Collection
    {
        return PlanShare::with('viewer')
            ->where('owner_id', $owner->id)
            ->where('status', PlanShareStatus::Accepted)
            ->orderByDesc('updated_at')
            ->get();
    }

    /** @return Collection<int, PlanShare> */
    public function followingFor(User $viewer): Collection
    {
        return PlanShare::with('owner')
            ->where('viewer_id', $viewer->id)
            ->where('status', PlanShareStatus::Accepted)
            ->orderByDesc('updated_at')
            ->get();
    }

    /** @return Collection<int, PlanShare> */
    public function pendingFor(User $user): Collection
    {
        return PlanShare::with(['owner', 'viewer', 'initiator'])
            ->where('status', PlanShareStatus::Pending)
            ->where(function ($q) use ($user) {
                $q->where(function ($q2) use ($user) {
                    $q2->where('direction', PlanShareDirection::ViewerRequests)
                        ->where('owner_id', $user->id);
                })->orWhere(function ($q2) use ($user) {
                    $q2->where('direction', PlanShareDirection::OwnerInvites)
                        ->where('viewer_id', $user->id);
                });
            })
            ->get();
    }

    public function shareBetween(User $owner, User $viewer): ?PlanShare
    {
        return PlanShare::where('owner_id', $owner->id)
            ->where('viewer_id', $viewer->id)
            ->first();
    }

    private function canRespond(User $user, PlanShare $share): bool
    {
        if ($share->status !== PlanShareStatus::Pending) {
            return false;
        }

        return ($share->direction === PlanShareDirection::ViewerRequests && $share->owner_id === $user->id)
            || ($share->direction === PlanShareDirection::OwnerInvites && $share->viewer_id === $user->id);
    }
}
