<?php

namespace App\Support;

class MacroEnergy
{
    public static function proteinFactor(): float
    {
        return (float) config('futurmeal.macro_energy_factors.protein', 4);
    }

    public static function carbsFactor(): float
    {
        return (float) config('futurmeal.macro_energy_factors.carbs', 4);
    }

    public static function fatFactor(): float
    {
        return (float) config('futurmeal.macro_energy_factors.fat', 9);
    }

    public static function kcalFromMacros(float $proteinG, float $carbsG, float $fatG): float
    {
        return ($proteinG * self::proteinFactor())
            + ($carbsG * self::carbsFactor())
            + ($fatG * self::fatFactor());
    }
}
