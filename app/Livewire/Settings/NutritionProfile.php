<?php

namespace App\Livewire\Settings;

use App\Enums\ActivityLevel;
use App\Enums\GoalType;
use App\Models\UserProfile;
use App\Services\Body\BodyMetricCalculator;
use App\Services\Nutrition\MealPlannerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class NutritionProfile extends Component
{
    public string $goal_type = '';

    public int $planning_horizon_days = 7;

    public string $activity_level = '';

    public int $calorie_adjustment = -400;

    public ?int $daily_calorie_target = null;

    public bool $override_calories = false;

    public ?float $target_weight_kg = null;

    public ?float $target_body_fat_percent = null;

    public ?int $maintenance_tdee = null;

    public ?int $basal_metabolic_rate = null;

    public function mount(): void
    {
        $profile = Auth::user()->profile;
        abort_unless($profile, 404);

        $this->goal_type = $profile->goal_type->value;
        $this->planning_horizon_days = $profile->planning_horizon_days;
        $this->activity_level = $profile->activity_level->value;
        $this->calorie_adjustment = $profile->calorie_adjustment;
        $this->daily_calorie_target = $profile->daily_calorie_target;
        $this->target_weight_kg = $profile->target_weight_kg;
        $this->target_body_fat_percent = $profile->target_body_fat_percent;

        $this->recalculateMaintenance();
    }

    public function updated($property): void
    {
        if ($property === 'activity_level') {
            $this->recalculateMaintenance();
        }
    }

    public function recalculateMaintenance(): void
    {
        $user = Auth::user();
        $profile = $user->profile;
        $latest = $user->bodyMetrics()->orderByDesc('recorded_at')->first();

        if (! $profile || ! $latest?->weight_kg) {
            return;
        }

        $age = $profile->birth_date?->age ?? 30;
        $calculator = app(BodyMetricCalculator::class);

        $this->basal_metabolic_rate = $calculator->bmr(
            $profile->gender,
            (float) $latest->weight_kg,
            (float) $profile->height_cm,
            $age,
        );

        $this->maintenance_tdee = $calculator->tdeeMifflinStJeor(
            $profile->gender,
            (float) $latest->weight_kg,
            (float) $profile->height_cm,
            $age,
            ActivityLevel::from($this->activity_level)->multiplier(),
            0,
        );
    }

    public function getEffectiveTargetProperty(): ?int
    {
        if ($this->override_calories && $this->daily_calorie_target) {
            return $this->daily_calorie_target;
        }

        return $this->maintenance_tdee !== null
            ? $this->maintenance_tdee + $this->calorie_adjustment
            : null;
    }

    public function getWeeklyKgProperty(): float
    {
        return round(abs($this->calorie_adjustment) * 7 / 7700, 2);
    }

    public function getMaintenanceWarningProperty(): bool
    {
        return ! $this->override_calories && abs($this->calorie_adjustment) < 100;
    }

    public function save(): void
    {
        $user = Auth::user();
        $profile = $user->profile;
        $latestWeight = $user->bodyMetrics()->orderByDesc('recorded_at')->value('weight_kg');

        $this->validate([
            'goal_type' => 'required|in:weight_loss,muscle_gain',
            'planning_horizon_days' => 'required|integer|in:3,7,14,30',
            'activity_level' => 'required',
            'calorie_adjustment' => 'required|integer|min:-1200|max:500',
            'daily_calorie_target' => 'nullable|integer|min:1000|max:6000',
            'target_weight_kg' => [
                'required', 'numeric', 'min:30', 'max:300',
                Rule::when($this->goal_type === GoalType::WeightLoss->value && $latestWeight, 'lt:'.$latestWeight),
                Rule::when($this->goal_type === GoalType::MuscleGain->value && $latestWeight, 'gt:'.$latestWeight),
            ],
            'target_body_fat_percent' => 'required|numeric|min:3|max:70',
        ]);

        $this->recalculateMaintenance();
        $target = $this->effectiveTarget ?? $profile->daily_calorie_target;

        UserProfile::where('user_id', $user->id)->update([
            'goal_type' => $this->goal_type,
            'planning_horizon_days' => $this->planning_horizon_days,
            'activity_level' => $this->activity_level,
            'calorie_adjustment' => $this->calorie_adjustment,
            'daily_calorie_target' => $target,
            'target_weight_kg' => $this->target_weight_kg,
            'target_body_fat_percent' => $this->target_body_fat_percent,
        ]);

        app(MealPlannerService::class)->ensureDefaultPlan($user->fresh());

        session()->flash('status', 'Paramètres nutrition mis à jour. Objectif : '.$target.' kcal/jour.');
    }

    public function render()
    {
        $activity = ActivityLevel::tryFrom($this->activity_level);

        return view('livewire.settings.nutrition-profile', [
            'goalOptions' => GoalType::cases(),
            'activityOptions' => ActivityLevel::cases(),
            'activityMultiplier' => $activity?->multiplier(),
            'activityLabel' => $activity?->label(),
        ]);
    }
}
