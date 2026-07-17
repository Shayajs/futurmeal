<?php

namespace App\Enums;

enum WheyPreference: string
{
    case None = 'none';
    case Concentrate = 'concentrate';
    case Isolate = 'isolate';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Pas de whey',
            self::Concentrate => 'Whey concentrée OK',
            self::Isolate => 'Whey isolat uniquement',
        };
    }

    public function promptLine(): string
    {
        return match ($this) {
            self::None => 'Ne pas proposer de whey / protéines en poudre.',
            self::Concentrate => 'La whey concentrée est autorisée (collations / post-entraînement).',
            self::Isolate => 'Uniquement whey isolat (pas de concentrée) si des protéines en poudre sont utiles.',
        };
    }
}
