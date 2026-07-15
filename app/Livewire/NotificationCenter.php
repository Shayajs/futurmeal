<?php

namespace App\Livewire;

use App\Services\Social\FriendshipService;
use App\Services\Social\PlanShareService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class NotificationCenter extends Component
{
    public array $acceptPlanCanEdit = [];

    public function markRead(string $notificationId): void
    {
        Auth::user()->notifications()->where('id', $notificationId)->first()?->markAsRead();
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public function acceptFriend(int $friendshipId, FriendshipService $friendships): void
    {
        $friendships->accept(Auth::user(), $friendshipId);
        $this->markRelatedRead('friend_request', $friendshipId);
    }

    public function rejectFriend(int $friendshipId, FriendshipService $friendships): void
    {
        $friendships->remove(Auth::user(), $friendshipId);
        $this->markRelatedRead('friend_request', $friendshipId);
    }

    public function acceptPlanShare(int $shareId, PlanShareService $planShares): void
    {
        $canEdit = $this->acceptPlanCanEdit[$shareId] ?? false;
        $planShares->accept(Auth::user(), $shareId, $canEdit);
        $this->markRelatedRead('plan_', $shareId, partial: true);
    }

    public function rejectPlanShare(int $shareId, PlanShareService $planShares): void
    {
        $planShares->reject(Auth::user(), $shareId);
        $this->markRelatedRead('plan_', $shareId, partial: true);
    }

    private function markRelatedRead(string $typePrefix, int $relatedId, bool $partial = false): void
    {
        Auth::user()->unreadNotifications->each(function ($n) use ($typePrefix, $relatedId, $partial) {
            $type = $n->data['type'] ?? '';
            $match = $partial ? str_starts_with($type, 'plan_') : ($type === $typePrefix);
            $idKey = str_contains($type, 'friend') ? 'friendship_id' : 'plan_share_id';
            if ($match && ($n->data[$idKey] ?? null) == $relatedId) {
                $n->markAsRead();
            }
        });
    }

    public function render()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->limit(50)->get();

        $friendNotifs = $notifications->filter(fn ($n) => str_starts_with($n->data['type'] ?? '', 'friend'));
        $planNotifs = $notifications->filter(fn ($n) => str_starts_with($n->data['type'] ?? '', 'plan_'));

        return view('livewire.notification-center', [
            'notifications' => $notifications,
            'friendNotifs' => $friendNotifs,
            'planNotifs' => $planNotifs,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }
}
