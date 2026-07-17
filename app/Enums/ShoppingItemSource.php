<?php

namespace App\Enums;

enum ShoppingItemSource: string
{
    case Aggregated = 'aggregated';
    case Custom = 'custom';
}
