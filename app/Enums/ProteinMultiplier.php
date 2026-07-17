<?php

namespace App\Enums;

enum ProteinMultiplier: string
{
    /** Maintien — ~1,7 g / kg de masse maigre */
    case Maintenance = '1.7';

    /** Standard — 2 g / kg MM */
    case Standard = '2.0';

    /** Max recommandé dans l’app — 2,2 g / kg MM */
    case Max = '2.2';

    public function factor(): float
    {
        return (float) $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => '×1,7 — maintien',
            self::Standard => '×2 — standard',
            self::Max => '×2,2 — max',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Maintenance => '×1,7',
            self::Standard => '×2',
            self::Max => '×2,2',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Maintenance => 'Cible protéines de maintien (masse maigre × 1,7).',
            self::Standard => 'Cible protéines classique (masse maigre × 2).',
            self::Max => 'Cible protéines élevée (masse maigre × 2,2) — plafond proposé dans FuturMeal.',
        };
    }
}
