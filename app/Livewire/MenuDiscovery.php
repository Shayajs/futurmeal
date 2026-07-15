<?php

namespace App\Livewire;

use App\Models\PublishedMenu;
use App\Services\Nutrition\MealPlannerService;
use App\Services\Social\FriendshipService;
use App\Services\Social\PublishedMenuService;
use App\Support\MealSlots;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class MenuDiscovery extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $applyingMenuId = null;

    public string $applyDate = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function startApply(int $menuId): void
    {
        $this->applyingMenuId = $menuId;
        $this->applyDate = today()->toDateString();
    }

    public function cancelApply(): void
    {
        $this->applyingMenuId = null;
    }

    public function apply(PublishedMenuService $menus, MealPlannerService $planner, FriendshipService $friendships): void
    {
        $this->validate(['applyDate' => 'required|date']);

        $menu = PublishedMenu::findOrFail($this->applyingMenuId);
        $user = Auth::user();

        abort_unless(
            $menu->is_public || $menu->user_id === $user->id || $friendships->areFriends($user, $menu->user),
            403,
        );

        $plan = $planner->ensureDefaultPlan($user);
        $count = $menus->applyToDay($user, $menu, $plan->id, $this->applyDate);

        $this->applyingMenuId = null;
        session()->flash('discover-status', "Menu « {$menu->title} » appliqué au ".\Carbon\Carbon::parse($this->applyDate)->translatedFormat('d F')." ({$count} aliments).");
    }

    public function unpublish(int $menuId): void
    {
        PublishedMenu::where('user_id', Auth::id())->where('id', $menuId)->delete();
    }

    public function render(FriendshipService $friendships)
    {
        $user = Auth::user();
        $friendIds = $friendships->friendIds($user);
        $term = trim($this->search);

        $friendMenus = PublishedMenu::with('user')
            ->whereIn('user_id', $friendIds)
            ->when($term, fn ($q) => $q->where('title', 'like', "%{$term}%"))
            ->latest()
            ->limit(6)
            ->get();

        $publicMenus = PublishedMenu::with('user')
            ->where('is_public', true)
            ->where('user_id', '!=', $user->id)
            ->whereNotIn('user_id', $friendIds)
            ->when($term, fn ($q) => $q->where('title', 'like', "%{$term}%"))
            ->orderByDesc('copies_count')
            ->latest()
            ->paginate(9);

        $myMenus = PublishedMenu::where('user_id', $user->id)->latest()->get();

        return view('livewire.menu-discovery', [
            'friendMenus' => $friendMenus,
            'publicMenus' => $publicMenus,
            'myMenus' => $myMenus,
            'slots' => MealSlots::ordered(),
        ]);
    }
}
