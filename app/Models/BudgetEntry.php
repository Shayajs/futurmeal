<?php

namespace App\Models;

use App\Enums\FoodReferenceType;
use App\Enums\PriceSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetEntry extends Model
{
    protected $fillable = [
        'user_id',
        'reference_type',
        'reference_id',
        'food_item_id',
        'label',
        'price_per_kg',
        'price_source',
        'store_brand',
    ];

    protected function casts(): array
    {
        return [
            'reference_type' => FoodReferenceType::class,
            'price_per_kg' => 'float',
            'price_source' => PriceSource::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
