<?php

namespace App\Services\Social;

use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendRequestAcceptedNotification;
use App\Notifications\FriendRequestNotification;
use Illuminate\Support\Collection;

class FriendshipService
{
    public function sendRequest(User $from, User $to): ?Friendship
    {
        if ($from->id === $to->id || $this->existingBetween($from, $to)) {
            return null;
        }

        $friendship = Friendship::create([
            'user_id' => $from->id,
            'friend_id' => $to->id,
            'status' => Friendship::STATUS_PENDING,
        ]);

        $friendship->load('user');
        $to->notify(new FriendRequestNotification($friendship));

        return $friendship;
    }

    public function accept(User $user, int $friendshipId): void
    {
        $friendship = Friendship::where('id', $friendshipId)
            ->where('friend_id', $user->id)
            ->where('status', Friendship::STATUS_PENDING)
            ->first();

        if (! $friendship) {
            return;
        }

        $friendship->update(['status' => Friendship::STATUS_ACCEPTED]);
        $friendship->load(['user', 'friend']);

        $friendship->user->notify(new FriendRequestAcceptedNotification($friendship->fresh()));
    }

    public function remove(User $user, int $friendshipId): void
    {
        Friendship::where('id', $friendshipId)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('friend_id', $user->id))
            ->delete();
    }

    public function areFriends(User $a, User $b): bool
    {
        return $this->existingBetween($a, $b)?->status === Friendship::STATUS_ACCEPTED;
    }

    /** @return Collection<int, User> */
    public function friendsOf(User $user): Collection
    {
        $accepted = Friendship::where('status', Friendship::STATUS_ACCEPTED)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('friend_id', $user->id))
            ->get();

        $ids = $accepted->map(
            fn (Friendship $f) => $f->user_id === $user->id ? $f->friend_id : $f->user_id
        );

        return User::whereIn('id', $ids)->orderBy('name')->get();
    }

    public function friendIds(User $user): array
    {
        return $this->friendsOf($user)->pluck('id')->all();
    }

    /** @return Collection<int, Friendship> */
    public function pendingReceived(User $user): Collection
    {
        return Friendship::with('user')
            ->where('friend_id', $user->id)
            ->where('status', Friendship::STATUS_PENDING)
            ->get();
    }

    /** @return Collection<int, Friendship> */
    public function pendingSent(User $user): Collection
    {
        return Friendship::with('friend')
            ->where('user_id', $user->id)
            ->where('status', Friendship::STATUS_PENDING)
            ->get();
    }

    private function existingBetween(User $a, User $b): ?Friendship
    {
        return Friendship::where(function ($q) use ($a, $b) {
            $q->where('user_id', $a->id)->where('friend_id', $b->id);
        })->orWhere(function ($q) use ($a, $b) {
            $q->where('user_id', $b->id)->where('friend_id', $a->id);
        })->first();
    }
}
