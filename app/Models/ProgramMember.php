<?php

namespace App\Models;

use App\Enums\ProgramMemberRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramMember extends Model
{
    protected $fillable = [
        'program_id',
        'user_id',
        'role',
        'share_metrics',
    ];

    protected function casts(): array
    {
        return [
            'role' => ProgramMemberRole::class,
            'share_metrics' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
