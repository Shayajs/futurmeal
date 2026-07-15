<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishedMenu extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'day_snapshot',
        'is_public',
        'copies_count',
    ];

    protected function casts(): array
    {
        return [
            'day_snapshot' => 'array',
            'is_public' => 'boolean',
            'copies_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function totalKcal(): int
    {
        $total = 0;
        foreach ($this->day_snapshot as $items) {
            foreach ($items as $item) {
                $total += (int) ($item['kcal'] ?? 0);
            }
        }

        return $total;
    }
}
