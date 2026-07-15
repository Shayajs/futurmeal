<?php

namespace App\Support;

class MealSlots
{
    public static function ordered(): array
    {
        return config('futurmeal.meal_slots', []);
    }

    public static function keys(): array
    {
        return array_keys(self::ordered());
    }

    public static function normalize(string $slot): string
    {
        $aliases = config('futurmeal.meal_slot_aliases', []);

        return $aliases[$slot] ?? $slot;
    }

    public static function label(string $slot): string
    {
        $slot = self::normalize($slot);

        return self::ordered()[$slot] ?? $slot;
    }

    public static function sortIndex(string $slot): int
    {
        $slot = self::normalize($slot);
        $index = array_search($slot, self::keys(), true);

        return $index === false ? 999 : $index;
    }

    public static function isValid(string $slot): bool
    {
        return array_key_exists($slot, self::ordered());
    }
}
