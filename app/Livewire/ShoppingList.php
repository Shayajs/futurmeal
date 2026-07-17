<?php

namespace App\Livewire;

use App\Enums\ShoppingItemSource;
use App\Services\Shopping\ShoppingListService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ShoppingList extends Component
{
    public string $rangeStart = '';

    public string $customLabel = '';

    public ?float $customQuantityG = null;

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->rangeStart = now()->startOfWeek()->toDateString();
    }

    public function previousPeriod(): void
    {
        $this->rangeStart = Carbon::parse($this->rangeStart)
            ->subDays($this->horizonDays())
            ->toDateString();
        $this->statusMessage = null;
    }

    public function nextPeriod(): void
    {
        $this->rangeStart = Carbon::parse($this->rangeStart)
            ->addDays($this->horizonDays())
            ->toDateString();
        $this->statusMessage = null;
    }

    public function resync(ShoppingListService $shopping): void
    {
        $this->currentList($shopping, forceSync: true);
        $this->statusMessage = 'Liste resynchronisée depuis le plan.';
    }

    public function toggleItem(int $itemId, bool $checked, ShoppingListService $shopping): void
    {
        $shopping->toggleChecked(Auth::user(), $itemId, $checked);
    }

    public function addCustom(ShoppingListService $shopping): void
    {
        $this->validate([
            'customLabel' => 'required|string|max:255',
            'customQuantityG' => 'nullable|numeric|min:0|max:100000',
        ], [
            'customLabel.required' => 'Indique un nom pour l’article.',
        ]);

        $list = $this->currentList($shopping);
        $shopping->addCustom(
            Auth::user(),
            $list,
            $this->customLabel,
            $this->customQuantityG,
        );

        $this->reset(['customLabel', 'customQuantityG']);
        $this->statusMessage = 'Article ajouté.';
    }

    public function deleteItem(int $itemId, ShoppingListService $shopping): void
    {
        $shopping->deleteItem(Auth::user(), $itemId);
        $this->statusMessage = 'Article retiré.';
    }

    public function uncheckAll(ShoppingListService $shopping): void
    {
        $list = $this->currentList($shopping);
        $shopping->uncheckAll(Auth::user(), $list);
        $this->statusMessage = 'Tout décoché.';
    }

    private function horizonDays(): int
    {
        return max(1, Auth::user()->profile?->planning_horizon_days ?? 7);
    }

    private function rangeEnd(): Carbon
    {
        return Carbon::parse($this->rangeStart)->addDays($this->horizonDays() - 1)->startOfDay();
    }

    private function currentList(ShoppingListService $shopping, bool $forceSync = false): \App\Models\ShoppingList
    {
        return $shopping->ensureForRange(
            Auth::user(),
            Carbon::parse($this->rangeStart)->startOfDay(),
            $this->rangeEnd(),
            $forceSync,
        );
    }

    public static function formatQuantity(?float $grams): string
    {
        if ($grams === null) {
            return '';
        }

        if ($grams >= 1000) {
            return number_format($grams / 1000, $grams % 1000 === 0.0 ? 0 : 1, ',', ' ').' kg';
        }

        return number_format($grams, $grams == (int) $grams ? 0 : 1, ',', ' ').' g';
    }

    public function render(ShoppingListService $shopping)
    {
        $list = $this->currentList($shopping);
        $items = $list->items;
        $checked = $items->where('is_checked', true)->count();
        $total = $items->count();
        $pending = $items->where('is_checked', false);
        $done = $items->where('is_checked', true);

        return view('livewire.shopping-list', [
            'list' => $list,
            'pendingItems' => $pending,
            'doneItems' => $done,
            'checkedCount' => $checked,
            'totalCount' => $total,
            'horizonDays' => $this->horizonDays(),
            'rangeEnd' => $this->rangeEnd()->toDateString(),
            'aggregatedSource' => ShoppingItemSource::Aggregated,
            'customSource' => ShoppingItemSource::Custom,
        ]);
    }
}
