<?php

namespace App\Enums;

enum FoodReferenceType: string
{
    case Ciqual = 'ciqual';
    case OpenFoodFacts = 'off';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Ciqual => 'CIQUAL',
            self::OpenFoodFacts => 'Open Food Facts',
            self::Custom => 'Personnalisé',
        };
    }
}
