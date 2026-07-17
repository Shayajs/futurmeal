<?php

namespace Tests\Feature;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use App\Enums\GoalType;
use App\Enums\MealComplexity;
use App\Enums\WheyPreference;
use App\Livewire\AiWeekGenerator;
use App\Livewire\Settings\NutritionProfile;
use App\Models\BodyMetric;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Nutrition\MealPlannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NutritionIntensityAndAiPrefsTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedUser(): User
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()]);
        UserProfile::create([
            'user_id' => $user->id,
            'gender' => Gender::Male,
            'birth_date' => '1990-01-01',
            'height_cm' => 180,
            'activity_level' => ActivityLevel::Moderate,
            'goal_type' => GoalType::WeightLoss->value,
            'planning_horizon_days' => 7,
            'daily_calorie_target' => 2000,
            'calorie_adjustment' => -400,
            'target_weight_kg' => 75,
            'target_body_fat_percent' => 15,
        ]);
        BodyMetric::create([
            'user_id' => $user->id,
            'recorded_at' => today(),
            'weight_kg' => 85,
        ]);

        return $user;
    }

    public function test_nutrition_settings_save_sport_and_intensity(): void
    {
        $user = $this->onboardedUser();

        Livewire::actingAs($user)
            ->test(NutritionProfile::class)
            ->set('goal_intensity', 'aggressive')
            ->assertSet('calorie_adjustment', -750)
            ->set('sport_kcal_per_day', 250)
            ->call('save')
            ->assertHasNoErrors();

        $profile = $user->fresh()->profile;
        $this->assertSame(250, $profile->sport_kcal_per_day);
        $this->assertSame('aggressive', $profile->goal_intensity->value);
        $this->assertSame(-750, $profile->calorie_adjustment);
    }

    public function test_nutrition_settings_save_protein_multiplier(): void
    {
        $user = $this->onboardedUser();
        BodyMetric::query()->where('user_id', $user->id)->update([
            'body_fat_percent' => 15,
            'lean_mass_kg' => 72.25,
        ]);

        Livewire::actingAs($user)
            ->test(NutritionProfile::class)
            ->set('protein_multiplier', '2.2')
            ->assertSet('proteinTargetG', 159)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('2.2', $user->fresh()->profile->protein_multiplier->value);
    }

    public function test_extreme_intensity_warns_but_does_not_clamp_target(): void
    {
        $user = $this->onboardedUser();

        $component = Livewire::actingAs($user)
            ->test(NutritionProfile::class)
            ->set('goal_intensity', 'extreme')
            ->assertSet('calorie_adjustment', -1000)
            ->assertSet('aggressivePaceWarning', true);

        $raw = $component->get('maintenance_tdee') + (-1000);
        $component
            ->assertSet('effectiveTarget', $raw)
            ->assertSee('Rythme agressif')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(-1000, $user->fresh()->profile->calorie_adjustment);
        $this->assertSame($raw, $user->fresh()->profile->daily_calorie_target);
    }

    public function test_ai_modal_prefills_and_saves_preferences(): void
    {
        $user = $this->onboardedUser();
        $user->profile->update([
            'ai_whey' => WheyPreference::Concentrate->value,
            'ai_meal_complexity' => MealComplexity::Gourmet->value,
            'ai_forbidden_foods' => ['porc'],
            'ai_preferred_foods' => ['poulet'],
            'ai_tasty_days_per_week' => 2,
            'ai_include_desserts' => true,
        ]);
        $plan = app(MealPlannerService::class)->ensureDefaultPlan($user);
        $weekStart = now()->startOfWeek()->toDateString();

        Livewire::actingAs($user)
            ->test(AiWeekGenerator::class, [
                'mealPlanId' => $plan->id,
                'weekStart' => $weekStart,
                'horizonDays' => 7,
                'canEdit' => true,
            ])
            ->call('open')
            ->assertSet('aiWhey', 'concentrate')
            ->assertSet('aiMealComplexity', 'gourmet')
            ->assertSet('forbiddenFoodsText', 'porc')
            ->assertSet('preferredFoodsText', 'poulet')
            ->assertSet('tastyDays', 2)
            ->assertSet('includeDesserts', true)
            ->set('aiWhey', 'isolate')
            ->set('forbiddenFoodsText', 'porc, arachides')
            ->set('includeDesserts', false)
            ->set('savePreferences', true)
            ->call('goToPrompt')
            ->assertSet('step', 'prompt')
            ->assertSee('arachides', false)
            ->assertSee('Whey isolat', false)
            ->assertSee('Desserts : Non', false);

        $profile = $user->fresh()->profile;
        $this->assertSame(WheyPreference::Isolate, $profile->ai_whey);
        $this->assertSame(['porc', 'arachides'], $profile->ai_forbidden_foods);
        $this->assertFalse($profile->ai_include_desserts);
    }
}
