<?php

namespace Tests\Unit;

use App\Enums\Gender;
use App\Services\Body\BodyMetricCalculator;
use PHPUnit\Framework\TestCase;

class BodyMetricCalculatorTest extends TestCase
{
    private BodyMetricCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BodyMetricCalculator;
    }

    public function test_bmi_calculation(): void
    {
        $bmi = $this->calculator->bmi(80, 180);
        $this->assertEqualsWithDelta(24.69, $bmi, 0.1);
    }

    public function test_navy_body_fat_male(): void
    {
        $bf = $this->calculator->navyBodyFatPercent(Gender::Male, 180, 38, 85);
        $this->assertGreaterThan(10, $bf);
        $this->assertLessThan(30, $bf);
    }

    public function test_lean_mass(): void
    {
        $lean = $this->calculator->leanMassKg(80, 20);
        $this->assertEquals(64.0, $lean);
    }

    public function test_weight_loss_projection(): void
    {
        $kg = $this->calculator->weightLossProjectionKg(7700);
        $this->assertEquals(1.0, $kg);
    }

    public function test_weekly_projection_weight_loss(): void
    {
        $result = $this->calculator->weeklyWeightProjection(7700, 'weight_loss');
        $this->assertEquals(1.0, $result['kg']);
        $this->assertSame('loss', $result['type']);
    }

    public function test_weekly_projection_muscle_gain(): void
    {
        $result = $this->calculator->weeklyWeightProjection(-7700, 'muscle_gain');
        $this->assertEquals(1.0, $result['kg']);
        $this->assertSame('gain', $result['type']);
    }
}
