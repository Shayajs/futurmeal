<?php

namespace App\Models;

use App\Enums\FoodReferenceType;
use App\Enums\ShoppingItemSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingListItem extends Model
{
    protected $fillable = [
        'shopping_list_id',
        'source',
        'aggregate_key',
        'label',
        'quantity_g',
        'reference_type',
        'reference_id',
        'food_item_id',
        'is_checked',
        'checked_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'source' => ShoppingItemSource::class,
            'reference_type' => FoodReferenceType::class,
            'quantity_g' => 'float',
            'is_checked' => 'boolean',
            'checked_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function foodItem(): BelongsTo
    {
        return $this->belongsTo(FoodItem::class);
    }
}
