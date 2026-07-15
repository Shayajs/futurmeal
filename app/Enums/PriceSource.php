<?php

namespace App\Enums;

enum PriceSource: string
{
    case User = 'user';
    case OpenPrices = 'open_prices';
    case Community = 'community';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Ton prix enregistré',
            self::OpenPrices => 'Open Prices',
            self::Community => 'Communauté',
        };
    }
}
