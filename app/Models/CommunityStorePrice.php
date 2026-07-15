<?php

namespace App\Models;

use App\Enums\FoodReferenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityStorePrice extends Model
{
    protected $fillable = [
        'user_id',
        'reference_type',
        'reference_id',
        'food_item_id',
        'label',
        'barcode',
        'store_brand',
        'open_prices_location_id',
        'price_per_kg',
        'observed_at',
    ];

    protected function casts(): array
    {
        return [
            'reference_type' => FoodReferenceType::class,
            'price_per_kg' => 'float',
            'observed_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function foodItem(): BelongsTo
    {
        return $this->belongsTo(FoodItem::class);
    }
}
