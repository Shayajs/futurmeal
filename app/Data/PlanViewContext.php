<?php

namespace App\Data;

use App\Models\User;

readonly class PlanViewContext
{
    public function __construct(
        public string $type,
        public User $viewer,
        public User $planOwner,
        public ?int $programId = null,
        public ?int $planShareId = null,
        public bool $canEdit = true,
    ) {}

    public function isSelf(): bool
    {
        return $this->type === 'self';
    }

    public function isFriend(): bool
    {
        return $this->type === 'friend';
    }

    public function isProgram(): bool
    {
        return $this->type === 'program';
    }

    public function label(): string
    {
        return match ($this->type) {
            'self' => 'Mon plan',
            'friend' => 'Plan de '.$this->planOwner->name,
            default => 'Programme',
        };
    }
}
