<?php

namespace App\Enums;

enum GoalIntensity: string
{
    case Soft = 'soft';
    case Moderate = 'moderate';
    case Aggressive = 'aggressive';
    case Extreme = 'extreme';

    public function label(): string
    {
        return match ($this) {
            self::Soft => 'Doux',
            self::Moderate => 'Modéré',
            self::Aggressive => 'Agressif',
            self::Extreme => 'Extrême',
        };
    }

    public function calorieAdjustment(GoalType $goal): int
    {
        return match ($goal) {
            GoalType::WeightLoss => match ($this) {
                self::Soft => -250,
                self::Moderate => -500,
                self::Aggressive => -750,
                self::Extreme => -1000,
            },
            GoalType::MuscleGain => match ($this) {
                self::Soft => 150,
                self::Moderate => 300,
                self::Aggressive => 400,
                self::Extreme => 500,
            },
        };
    }

    public function disclaimer(): ?string
    {
        return match ($this) {
            self::Extreme => 'Niveau Extrême : déficit/surplus maximal dans les limites indicatives ACSM. Non adapté à tous les profils. Consulte un professionnel de santé avant de poursuivre.',
            self::Aggressive => 'Niveau Agressif : exige une bonne adhérence (sommeil, protéines, entraînement). Surveille ta fatigue et ton humeur.',
            default => null,
        };
    }
}
