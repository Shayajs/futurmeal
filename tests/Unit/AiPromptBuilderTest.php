<?php

namespace Tests\Unit;

use App\Data\AiMealPreferences;
use App\Enums\GoalType;
use App\Enums\MealComplexity;
use App\Enums\WheyPreference;
use App\Models\Recipe;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Ai\AiPromptBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_prompt_with_dates_slots_and_recipes(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id,
            'goal_type' => GoalType::MuscleGain->value,
            'daily_calorie_target' => 2800,
            'planning_horizon_days' => 7,
            'calorie_adjustment' => 300,
        ]);
        Recipe::create([
            'user_id' => $user->id,
            'name' => 'Poulet riz',
            'servings' => 1,
        ]);

        $built = app(AiPromptBuilder::class)->build(
            $user,
            '2026-07-20',
            2,
            new AiMealPreferences(
                whey: WheyPreference::Isolate,
                mealComplexity: MealComplexity::FastTasty,
                forbiddenFoods: ['porc', 'lactose'],
                preferredFoods: ['poulet'],
                tastyDays: 1,
                includeDesserts: true,
                freeInstructions: 'Pas de lactose',
            ),
        );

        $this->assertStringContainsString('2800', $built['system']);
        $this->assertStringContainsString('2026-07-20', $built['user']);
        $this->assertStringContainsString('2026-07-21', $built['user']);
        $this->assertStringContainsString('Poulet riz', $built['user']);
        $this->assertStringContainsString('Pas de lactose', $built['user']);
        $this->assertStringContainsString('porc', $built['user']);
        $this->assertStringContainsString('Whey isolat', $built['user']);
        $this->assertStringContainsString('Rapide mais goûtu', $built['user']);
        $this->assertStringContainsString('dessert', mb_strtolower($built['user']));
        $this->assertStringContainsString('Inclure des desserts : oui', $built['system']);
        $this->assertStringContainsString('breakfast', $built['user']);
        $this->assertSame($built['full'], trim($built['system'])."\n\n---\n\n".trim($built['user']));
    }
}
