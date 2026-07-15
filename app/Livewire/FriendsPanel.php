<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Social\FriendshipService;
use App\Services\Social\PlanShareService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class FriendsPanel extends Component
{
    public string $search = '';

    public array $searchResults = [];

    public string $inviteCode = '';

    public bool $inviteCanEdit = false;

    public function requestFollowPlan(int $friendId, PlanShareService $planShares): void
    {
        $friend = User::findOrFail($friendId);
        $result = $planShares->requestToFollow(Auth::user(), $friend);

        session()->flash('friends-status', $result
            ? "Demande de suivi envoyée à {$friend->name}."
            : "Impossible d'envoyer la demande (déjà en cours ou non ami).");
    }

    public function inviteFollowPlan(int $friendId, PlanShareService $planShares): void
    {
        $friend = User::findOrFail($friendId);
        $result = $planShares->inviteToFollow(Auth::user(), $friend, $this->inviteCanEdit);

        session()->flash('friends-status', $result
            ? "Invitation à suivre ton plan envoyée à {$friend->name}."
            : "Impossible d'envoyer l'invitation (déjà en cours ou non ami).");
    }

    public function updatedSearch(): void
    {
        $term = trim($this->search);

        if (strlen($term) < 2) {
            $this->searchResults = [];

            return;
        }

        $this->searchResults = User::query()
            ->where('id', '!=', Auth::id())
            ->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"))
            ->limit(8)
            ->get(['id', 'name', 'email'])
            ->toArray();
    }

    public function sendRequest(int $userId, FriendshipService $friendships): void
    {
        $target = User::findOrFail($userId);
        $result = $friendships->sendRequest(Auth::user(), $target);

        session()->flash('friends-status', $result
            ? "Demande envoyée à {$target->name}."
            : "Vous êtes déjà liés à {$target->name} (ami ou demande en cours).");

        $this->search = '';
        $this->searchResults = [];
    }

    public function addByCode(FriendshipService $friendships): void
    {
        $code = strtoupper(trim($this->inviteCode));
        $this->validate(['inviteCode' => 'required|string|min:4']);

        $target = User::where('friend_code', $code)->first();

        if (! $target || $target->id === Auth::id()) {
            $this->addError('inviteCode', 'Code ami introuvable.');

            return;
        }

        $result = $friendships->sendRequest(Auth::user(), $target);
        session()->flash('friends-status', $result
            ? "Demande envoyée à {$target->name}."
            : "Vous êtes déjà liés à {$target->name}.");
        $this->inviteCode = '';
    }

    public function accept(int $friendshipId, FriendshipService $friendships): void
    {
        $friendships->accept(Auth::user(), $friendshipId);
    }

    public function remove(int $friendshipId, FriendshipService $friendships): void
    {
        $friendships->remove(Auth::user(), $friendshipId);
    }

    public function render(FriendshipService $friendships)
    {
        $user = Auth::user();

        return view('livewire.friends-panel', [
            'friends' => $friendships->friendsOf($user),
            'pendingReceived' => $friendships->pendingReceived($user),
            'pendingSent' => $friendships->pendingSent($user),
            'friendCode' => $user->friend_code,
            'shareLink' => route('friends.add', ['code' => $user->friend_code]),
        ]);
    }
}
