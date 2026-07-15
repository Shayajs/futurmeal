<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Program extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'invite_code',
        'lock_portions',
        'week_starts_on',
    ];

    protected function casts(): array
    {
        return [
            'lock_portions' => 'boolean',
            'week_starts_on' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Program $program) {
            if (empty($program->invite_code)) {
                $program->invite_code = strtoupper(Str::random(8));
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProgramMember::class);
    }

    public function mealPlans(): HasMany
    {
        return $this->hasMany(MealPlan::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProgramInvitation::class);
    }
}
