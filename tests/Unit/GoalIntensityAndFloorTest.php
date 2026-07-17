<?php

namespace Tests\Unit;

use App\Enums\Gender;
use App\Enums\GoalIntensity;
use App\Enums\GoalType;
use App\Services\Body\BodyMetricCalculator;
use Tests\TestCase;

class GoalIntensityAndFloorTest extends TestCase
{
    public function test_intensity_maps_to_calorie_adjustments(): void
    {
        $this->assertSame(-250, GoalIntensity::Soft->calorieAdjustment(GoalType::WeightLoss));
        $this->assertSame(-500, GoalIntensity::Moderate->calorieAdjustment(GoalType::WeightLoss));
        $this->assertSame(-750, GoalIntensity::Aggressive->calorieAdjustment(GoalType::WeightLoss));
        $this->assertSame(-1000, GoalIntensity::Extreme->calorieAdjustment(GoalType::WeightLoss));

        $this->assertSame(150, GoalIntensity::Soft->calorieAdjustment(GoalType::MuscleGain));
        $this->assertSame(300, GoalIntensity::Moderate->calorieAdjustment(GoalType::MuscleGain));
        $this->assertSame(400, GoalIntensity::Aggressive->calorieAdjustment(GoalType::MuscleGain));
        $this->assertSame(500, GoalIntensity::Extreme->calorieAdjustment(GoalType::MuscleGain));
    }

    public function test_floor_daily_kcal_by_gender_and_bmr(): void
    {
        $calc = app(BodyMetricCalculator::class);

        $this->assertSame(1500, $calc->floorDailyKcal(Gender::Male));
        $this->assertSame(1200, $calc->floorDailyKcal(Gender::Female));
        $this->assertSame(1800, $calc->floorDailyKcal(Gender::Male, 1800));
        $this->assertSame(1500, $calc->floorDailyKcal(Gender::Male, 1400));
    }

    public function test_maintenance_includes_sport_kcal(): void
    {
        $calc = app(BodyMetricCalculator::class);
        $base = $calc->maintenanceTdee(Gender::Male, 80, 180, 30, 1.55, 0);
        $withSport = $calc->maintenanceTdee(Gender::Male, 80, 180, 30, 1.55, 300);

        $this->assertSame($base + 300, $withSport);
    }

    public function test_bmr_safety_margin_is_ten_percent(): void
    {
        $calc = app(BodyMetricCalculator::class);
        $raw = $calc->bmr(Gender::Male, 80, 180, 30);
        $safe = $calc->bmrWithSafety(Gender::Male, 80, 180, 30);

        $this->assertSame((int) round($raw * 0.9), $safe);
        $this->assertSame(
            (int) round($safe * 1.55),
            $calc->maintenanceTdee(Gender::Male, 80, 180, 30, 1.55, 0),
        );
    }

    public function test_clamp_helper_raises_below_floor_when_used(): void
    {
        $calc = app(BodyMetricCalculator::class);

        $this->assertTrue($calc->isBelowFloor(900, Gender::Male, 1400));
        $this->assertSame(1500, $calc->clampDailyTarget(900, Gender::Male, 1400));
        $this->assertSame(2000, $calc->clampDailyTarget(2000, Gender::Male, 1400));
    }

    public function test_extreme_and_aggressive_have_disclaimers(): void
    {
        $this->assertNotNull(GoalIntensity::Extreme->disclaimer());
        $this->assertNotNull(GoalIntensity::Aggressive->disclaimer());
        $this->assertNull(GoalIntensity::Soft->disclaimer());
    }
}
