<?php

namespace App\Enums;

enum GoalType: string
{
    case WeightLoss = 'weight_loss';
    case MuscleGain = 'muscle_gain';

    public function label(): string
    {
        return match ($this) {
            self::WeightLoss => 'Perte de poids',
            self::MuscleGain => 'Gain de masse',
        };
    }

    public function defaultCalorieAdjustment(): int
    {
        return match ($this) {
            self::WeightLoss => -400,
            self::MuscleGain => 300,
        };
    }
}
