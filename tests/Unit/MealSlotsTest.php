<?php

namespace Tests\Unit;

use App\Support\MealSlots;
use Tests\TestCase;

class MealSlotsTest extends TestCase
{
    public function test_has_six_ordered_slots(): void
    {
        $this->assertCount(6, MealSlots::ordered());
        $this->assertSame([
            'morning_snack',
            'breakfast',
            'lunch',
            'afternoon_snack',
            'dinner',
            'night_snack',
        ], MealSlots::keys());
    }

    public function test_normalizes_legacy_snack_key(): void
    {
        $this->assertSame('afternoon_snack', MealSlots::normalize('snack'));
        $this->assertSame('Goûter', MealSlots::label('snack'));
    }
}
