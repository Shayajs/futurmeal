<?php

namespace Tests\Unit;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use App\Enums\GoalType;
use App\Enums\ProteinMultiplier;
use App\Models\BodyMetric;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Nutrition\ProteinTargetCalculator;
use App\Support\MacroEnergy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProteinTargetAndMacroEnergyTest extends TestCase
{
    use RefreshDatabase;

    public function test_macro_energy_uses_config_factors(): void
    {
        $this->assertSame(4.0, MacroEnergy::proteinFactor());
        $this->assertSame(4.0, MacroEnergy::carbsFactor());
        $this->assertSame(9.0, MacroEnergy::fatFactor());
        $this->assertSame(121.0, MacroEnergy::kcalFromMacros(25, 3, 1));
    }

    public function test_protein_target_from_lean_mass_and_multiplier(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id,
            'gender' => Gender::Male,
            'birth_date' => '1990-01-01',
            'height_cm' => 180,
            'activity_level' => ActivityLevel::Moderate,
            'goal_type' => GoalType::MuscleGain->value,
            'planning_horizon_days' => 7,
            'daily_calorie_target' => 2800,
            'calorie_adjustment' => 300,
            'protein_multiplier' => ProteinMultiplier::Max->value,
            'target_weight_kg' => 85,
            'target_body_fat_percent' => 12,
        ]);
        BodyMetric::create([
            'user_id' => $user->id,
            'recorded_at' => today(),
            'weight_kg' => 80,
            'body_fat_percent' => 15,
            'lean_mass_kg' => 68,
        ]);

        $calc = app(ProteinTargetCalculator::class);

        $this->assertSame(68.0, $calc->leanMassKg($user));
        // 68 * 2.2 = 149.6 → 150
        $this->assertSame(150, $calc->dailyTargetG($user));
        $this->assertSame(136, $calc->dailyTargetG($user, ProteinMultiplier::Standard));
        $this->assertSame(116, $calc->dailyTargetG($user, ProteinMultiplier::Maintenance));
    }
}
