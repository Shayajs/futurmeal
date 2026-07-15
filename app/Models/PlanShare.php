<?php

namespace App\Models;

use App\Enums\PlanShareDirection;
use App\Enums\PlanShareStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanShare extends Model
{
    protected $fillable = [
        'owner_id',
        'viewer_id',
        'initiated_by',
        'direction',
        'status',
        'can_edit',
    ];

    protected function casts(): array
    {
        return [
            'direction' => PlanShareDirection::class,
            'status' => PlanShareStatus::class,
            'can_edit' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function isActive(): bool
    {
        return $this->status === PlanShareStatus::Accepted;
    }
}
