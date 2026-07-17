<?php

namespace App\Services\Nutrition;

use App\Enums\ProteinMultiplier;
use App\Models\User;
use App\Services\Body\BodyMetricCalculator;

class ProteinTargetCalculator
{
    public function __construct(
        private BodyMetricCalculator $body,
    ) {}

    public function multiplier(User $user): ProteinMultiplier
    {
        return $user->profile?->protein_multiplier ?? ProteinMultiplier::Maintenance;
    }

    /**
     * Masse maigre (kg) : lean_mass stockée, sinon poids × (1 − % graisse), sinon poids × 0,75.
     */
    public function leanMassKg(User $user): ?float
    {
        $latest = $user->bodyMetrics()->orderByDesc('recorded_at')->first();
        if (! $latest?->weight_kg) {
            return null;
        }

        $weight = (float) $latest->weight_kg;

        if ($latest->lean_mass_kg !== null && (float) $latest->lean_mass_kg > 0) {
            return (float) $latest->lean_mass_kg;
        }

        if ($latest->body_fat_percent !== null) {
            return $this->body->leanMassKg($weight, (float) $latest->body_fat_percent);
        }

        return round($weight * 0.75, 2);
    }

    public function dailyTargetG(User $user, ?ProteinMultiplier $multiplier = null): ?int
    {
        $lean = $this->leanMassKg($user);
        if ($lean === null || $lean <= 0) {
            return null;
        }

        $factor = ($multiplier ?? $this->multiplier($user))->factor();

        return (int) max(1, round($lean * $factor));
    }
}
