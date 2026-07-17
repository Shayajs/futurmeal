<?php

namespace App\Enums;

enum MealComplexity: string
{
    case SimpleBudget = 'simple_budget';
    case SimpleTight = 'simple_tight';
    case FastTasty = 'fast_tasty';
    case Complex = 'complex';
    case Gourmet = 'gourmet';

    public function label(): string
    {
        return match ($this) {
            self::SimpleBudget => 'Simple, avec du budget',
            self::SimpleTight => 'Simple, budget serré',
            self::FastTasty => 'Rapide mais goûtu',
            self::Complex => 'Plats complexes (temps)',
            self::Gourmet => 'Gastronomique',
        };
    }

    public function promptLine(): string
    {
        return match ($this) {
            self::SimpleBudget => 'Plats simples, ingrédients courants, budget confortable (pas de produits premium inutiles).',
            self::SimpleTight => 'Plats très simples et économiques (légumes de saison, féculents basiques, peu de gaspillage).',
            self::FastTasty => 'Préparation rapide (<30 min) mais savoureuse (épices, marinades courtes, bons assaisonnements).',
            self::Complex => 'Plats élaborés acceptés (marinades longues, cuissons lentes, plusieurs étapes).',
            self::Gourmet => 'Orientation gastronomique : techniques soignées, associations de saveurs, présentation soignée — toujours dans la cible kcal.',
        };
    }
}
