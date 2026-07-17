<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShoppingList extends Model
{
    protected $fillable = [
        'user_id',
        'range_start',
        'range_end',
    ];

    protected function casts(): array
    {
        return [
            'range_start' => 'date',
            'range_end' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShoppingListItem::class)->orderBy('sort_order')->orderBy('label');
    }
}
