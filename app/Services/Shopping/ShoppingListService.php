<?php

namespace App\Services\Shopping;

use App\Enums\ShoppingItemSource;
use App\Models\ShoppingList;
use App\Models\ShoppingListItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ShoppingListService
{
    public function __construct(
        private ShoppingListAggregator $aggregator,
    ) {}

    public function ensureForRange(User $user, Carbon $rangeStart, Carbon $rangeEnd, bool $forceSync = false): ShoppingList
    {
        $start = $rangeStart->toDateString();
        $end = $rangeEnd->toDateString();

        $list = ShoppingList::query()
            ->where('user_id', $user->id)
            ->whereDate('range_start', $start)
            ->whereDate('range_end', $end)
            ->first();

        $created = false;
        if (! $list) {
            $list = ShoppingList::query()->create([
                'user_id' => $user->id,
                'range_start' => $start,
                'range_end' => $end,
            ]);
            $created = true;
        }

        $needsSync = $forceSync
            || $created
            || ! $list->items()->where('source', ShoppingItemSource::Aggregated)->exists();

        if ($needsSync) {
            $this->syncAggregated($user, $list);
        }

        return $list->fresh('items');
    }

    public function syncAggregated(User $user, ShoppingList $list): void
    {
        $aggregated = $this->aggregator->aggregate(
            $user,
            $list->range_start->copy()->startOfDay(),
            $list->range_end->copy()->startOfDay(),
        );

        $keys = collect($aggregated)->pluck('key')->all();

        DB::transaction(function () use ($list, $aggregated) {
            $existing = $list->items()
                ->where('source', ShoppingItemSource::Aggregated)
                ->get()
                ->keyBy('aggregate_key');

            $sort = 0;
            foreach ($aggregated as $row) {
                $item = $existing->get($row['key']);
                if ($item) {
                    $item->update([
                        'label' => $row['label'],
                        'quantity_g' => $row['quantity_g'],
                        'reference_type' => $row['reference_type'],
                        'reference_id' => $row['reference_id'],
                        'food_item_id' => $row['food_item_id'],
                        'sort_order' => $sort++,
                    ]);
                    $existing->forget($row['key']);
                    continue;
                }

                $list->items()->create([
                    'source' => ShoppingItemSource::Aggregated,
                    'aggregate_key' => $row['key'],
                    'label' => $row['label'],
                    'quantity_g' => $row['quantity_g'],
                    'reference_type' => $row['reference_type'],
                    'reference_id' => $row['reference_id'],
                    'food_item_id' => $row['food_item_id'],
                    'is_checked' => false,
                    'sort_order' => $sort++,
                ]);
            }

            // Remove unchecked aggregated items no longer in the plan.
            // Keep checked orphans so the user doesn't lose "already bought" state.
            foreach ($existing as $orphan) {
                if ($orphan->is_checked) {
                    $orphan->update([
                        'sort_order' => $sort++,
                    ]);
                    continue;
                }

                $orphan->delete();
            }
        });
    }

    public function toggleChecked(User $user, int $itemId, bool $checked): ShoppingListItem
    {
        $item = $this->findOwnedItem($user, $itemId);
        $item->update([
            'is_checked' => $checked,
            'checked_at' => $checked ? now() : null,
        ]);

        return $item->fresh();
    }

    public function addCustom(User $user, ShoppingList $list, string $label, ?float $quantityG = null): ShoppingListItem
    {
        abort_unless($list->user_id === $user->id, 403);

        $maxSort = (int) $list->items()->max('sort_order');

        return $list->items()->create([
            'source' => ShoppingItemSource::Custom,
            'aggregate_key' => null,
            'label' => trim($label),
            'quantity_g' => $quantityG,
            'is_checked' => false,
            'sort_order' => $maxSort + 1,
        ]);
    }

    public function deleteItem(User $user, int $itemId): void
    {
        $item = $this->findOwnedItem($user, $itemId);
        // Allow deleting custom always; aggregated only if user insists (UI only shows delete on custom)
        $item->delete();
    }

    public function uncheckAll(User $user, ShoppingList $list): void
    {
        abort_unless($list->user_id === $user->id, 403);

        $list->items()->where('is_checked', true)->update([
            'is_checked' => false,
            'checked_at' => null,
        ]);
    }

    private function findOwnedItem(User $user, int $itemId): ShoppingListItem
    {
        return ShoppingListItem::query()
            ->whereKey($itemId)
            ->whereHas('shoppingList', fn ($q) => $q->where('user_id', $user->id))
            ->firstOrFail();
    }
}
