<?php

namespace Tests\Unit;

use App\Enums\GoalType;
use App\Models\UserProfile;
use PHPUnit\Framework\TestCase;

class UserProfileGoalProgressTest extends TestCase
{
    public function test_weight_loss_progress(): void
    {
        $profile = new UserProfile([
            'goal_type' => GoalType::WeightLoss,
            'target_weight_kg' => 75,
        ]);

        $this->assertSame(30, $profile->weightGoalProgress(82, 85));
        $this->assertSame(100, $profile->weightGoalProgress(74, 85));
    }

    public function test_muscle_gain_progress(): void
    {
        $profile = new UserProfile([
            'goal_type' => GoalType::MuscleGain,
            'target_weight_kg' => 78,
        ]);

        $this->assertSame(25, $profile->weightGoalProgress(72, 70));
    }

    public function test_body_fat_progress(): void
    {
        $profile = new UserProfile([
            'goal_type' => GoalType::WeightLoss,
            'target_body_fat_percent' => 12,
        ]);

        $this->assertSame(25, $profile->bodyFatGoalProgress(18, 20));
    }
}
