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

        if (! $list) {
            $list = ShoppingList::query()->create([
                'user_id' => $user->id,
                'range_start' => $start,
                'range_end' => $end,
            ]);
        }

        // Always refresh aggregated lines so duplicates merge and qty stay current.
        $this->syncAggregated($user, $list);

        return $list->fresh('items');
    }

    public function syncAggregated(User $user, ShoppingList $list): void
    {
        $aggregated = $this->aggregator->aggregate(
            $user,
            $list->range_start->copy()->startOfDay(),
            $list->range_end->copy()->startOfDay(),
        );

        DB::transaction(function () use ($list, $aggregated) {
            $existingItems = $list->items()
                ->where('source', ShoppingItemSource::Aggregated)
                ->get();

            $byKey = $existingItems->keyBy('aggregate_key');
            $byLabel = $existingItems->groupBy(
                fn (ShoppingListItem $item) => $this->aggregator->normalizeLabel($item->label)
            );

            $sort = 0;
            $seenIds = [];

            foreach ($aggregated as $row) {
                $norm = $this->aggregator->normalizeLabel($row['label']);
                $item = $byKey->get($row['key']);

                if (! $item) {
                    $candidates = $byLabel->get($norm, collect());
                    $item = $candidates->first(fn (ShoppingListItem $i) => ! isset($seenIds[$i->id]));
                }

                if ($item && ! isset($seenIds[$item->id])) {
                    // Merge checked state if several old duplicates matched this label
                    $wasChecked = $item->is_checked;
                    $siblings = ($byLabel->get($norm) ?? collect())
                        ->filter(fn (ShoppingListItem $i) => $i->id !== $item->id && ! isset($seenIds[$i->id]));

                    foreach ($siblings as $sibling) {
                        if ($sibling->is_checked) {
                            $wasChecked = true;
                        }
                        $sibling->delete();
                        $seenIds[$sibling->id] = true;
                    }

                    $item->update([
                        'aggregate_key' => $row['key'],
                        'label' => $row['label'],
                        'quantity_g' => $row['quantity_g'],
                        'reference_type' => $row['reference_type'],
                        'reference_id' => $row['reference_id'],
                        'food_item_id' => $row['food_item_id'],
                        'is_checked' => $wasChecked,
                        'checked_at' => $wasChecked ? ($item->checked_at ?? now()) : null,
                        'sort_order' => $sort++,
                    ]);
                    $seenIds[$item->id] = true;
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

            foreach ($existingItems as $orphan) {
                if (isset($seenIds[$orphan->id])) {
                    continue;
                }

                // Refresh in case deleted as sibling
                if (! ShoppingListItem::query()->whereKey($orphan->id)->exists()) {
                    continue;
                }

                if ($orphan->is_checked) {
                    $orphan->update(['sort_order' => $sort++]);
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
