<?php

namespace App\Enums;

enum BodyMetricSource: string
{
    case Manual = 'manual';
    case Navy = 'navy';
    case Scale = 'scale';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Saisie manuelle',
            self::Navy => 'Méthode Navy',
            self::Scale => 'Balance connectée',
        };
    }
}
