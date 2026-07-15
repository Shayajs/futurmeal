<?php

namespace App\Livewire\Onboarding;

use App\Enums\ActivityLevel;
use App\Enums\BodyMetricSource;
use App\Enums\Gender;
use App\Enums\GoalType;
use App\Models\BodyMetric;
use App\Models\UserProfile;
use App\Services\Body\BodyMetricCalculator;
use App\Services\LogSnag\LogSnagClient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.guest')]
class ProfileSetup extends Component
{
    public int $step = 1;

    public string $goal_type = GoalType::WeightLoss->value;

    public int $planning_horizon_days = 7;

    public string $gender = Gender::Male->value;

    public ?string $birth_date = null;

    public ?float $height_cm = null;

    public ?float $weight_kg = null;

    public string $activity_level = ActivityLevel::Moderate->value;

    public string $metric_source = BodyMetricSource::Manual->value;

    public ?float $body_fat_percent = null;

    public ?float $neck_cm = null;

    public ?float $waist_cm = null;

    public ?float $hip_cm = null;

    public ?float $target_weight_kg = null;

    public ?float $target_body_fat_percent = null;

    public function nextStep(BodyMetricCalculator $calculator): void
    {
        if ($this->step === 1) {
            $this->validate([
                'goal_type' => 'required|in:weight_loss,muscle_gain',
                'planning_horizon_days' => 'required|integer|in:3,7,14,30',
            ]);
        }

        if ($this->step === 2) {
            $this->validate([
                'gender' => 'required|in:male,female,other',
                'birth_date' => 'required|date|before:today',
                'height_cm' => 'required|numeric|min:100|max:250',
                'weight_kg' => 'required|numeric|min:30|max:300',
                'activity_level' => 'required',
            ]);
        }

        if ($this->step === 3) {
            $rules = ['metric_source' => 'required'];

            if ($this->metric_source === BodyMetricSource::Manual->value) {
                $rules['body_fat_percent'] = 'nullable|numeric|min:3|max:70';
            } else {
                $rules['neck_cm'] = 'required|numeric|min:20|max:80';
                $rules['waist_cm'] = 'required|numeric|min:40|max:200';
                if ($this->gender === Gender::Female->value) {
                    $rules['hip_cm'] = 'required|numeric|min:50|max:200';
                }
            }

            $this->validate($rules);
            $this->prefillBodyTargets($calculator);
        }

        if ($this->step === 4) {
            $this->validateBodyTargets();
        }

        $this->step = min(5, $this->step + 1);
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function getGoalLabelProperty(): string
    {
        return GoalType::tryFrom($this->goal_type)?->label() ?? GoalType::WeightLoss->label();
    }

    public function getMetricSourceLabelProperty(): string
    {
        return BodyMetricSource::tryFrom($this->metric_source)?->label() ?? BodyMetricSource::Manual->label();
    }

    public function getCurrentBodyFatProperty(): ?float
    {
        return $this->resolveCurrentBodyFat(app(BodyMetricCalculator::class));
    }

    public function getStepTitleProperty(): string
    {
        return match ($this->step) {
            1 => 'Objectif',
            2 => 'Profil',
            3 => 'Composition actuelle',
            4 => 'Objectifs corporels',
            default => 'Récapitulatif',
        };
    }

    public function complete(BodyMetricCalculator $calculator, LogSnagClient $logSnag): void
    {
        $this->validate([
            'goal_type' => 'required|in:weight_loss,muscle_gain',
            'planning_horizon_days' => 'required|integer|in:3,7,14,30',
            'gender' => 'required|in:male,female,other',
            'birth_date' => 'required|date|before:today',
            'height_cm' => 'required|numeric|min:100|max:250',
            'weight_kg' => 'required|numeric|min:30|max:300',
            'activity_level' => 'required',
            'metric_source' => 'required|in:manual,navy,scale',
        ]);

        $this->validateBodyTargets();

        $user = Auth::user();
        $gender = Gender::from($this->gender);
        $goal = GoalType::from($this->goal_type);
        $source = BodyMetricSource::from($this->metric_source);

        $bodyFat = $this->resolveCurrentBodyFat($calculator);

        $age = now()->diffInYears($this->birth_date);
        $adjustment = $goal->defaultCalorieAdjustment();
        $tdee = $calculator->tdeeMifflinStJeor(
            $gender,
            (float) $this->weight_kg,
            (float) $this->height_cm,
            $age,
            ActivityLevel::from($this->activity_level)->multiplier(),
            $adjustment,
        );

        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'gender' => $gender,
                'birth_date' => $this->birth_date,
                'height_cm' => $this->height_cm,
                'activity_level' => $this->activity_level,
                'goal_type' => $goal,
                'planning_horizon_days' => $this->planning_horizon_days,
                'daily_calorie_target' => $tdee,
                'calorie_adjustment' => $adjustment,
                'target_weight_kg' => $this->target_weight_kg,
                'target_body_fat_percent' => $this->target_body_fat_percent,
            ]
        );

        $bmi = $calculator->bmi((float) $this->weight_kg, (float) $this->height_cm);
        $leanMass = $bodyFat !== null
            ? $calculator->leanMassKg((float) $this->weight_kg, $bodyFat)
            : null;

        BodyMetric::updateOrCreate(
            [
                'user_id' => $user->id,
                'recorded_at' => now()->toDateString(),
            ],
            [
                'weight_kg' => $this->weight_kg,
                'body_fat_percent' => $bodyFat,
                'lean_mass_kg' => $leanMass,
                'bmi' => $bmi,
                'source' => $source,
                'neck_cm' => $this->neck_cm,
                'waist_cm' => $this->waist_cm,
                'hip_cm' => $this->hip_cm,
            ]
        );

        $user->update(['onboarding_completed_at' => now()]);

        $logSnag->log('users', 'Nouvelle inscription', $user->email, '🥗', false, [
            'goal' => $goal->value,
            'horizon' => (string) $this->planning_horizon_days,
            'target_weight' => (string) $this->target_weight_kg,
            'target_body_fat' => (string) $this->target_body_fat_percent,
        ], (string) $user->id);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function render()
    {
        return view('livewire.onboarding.profile-setup');
    }

    private function prefillBodyTargets(BodyMetricCalculator $calculator): void
    {
        if ($this->target_weight_kg === null && $this->weight_kg !== null) {
            $this->target_weight_kg = $this->goal_type === GoalType::WeightLoss->value
                ? max(30, round($this->weight_kg - 5, 1))
                : round($this->weight_kg + 3, 1);
        }

        $currentBodyFat = $this->resolveCurrentBodyFat($calculator);

        if ($this->target_body_fat_percent === null && $currentBodyFat !== null) {
            $this->target_body_fat_percent = $this->goal_type === GoalType::WeightLoss->value
                ? max(5, round($currentBodyFat - 3, 1))
                : max(5, round($currentBodyFat - 1, 1));
        }
    }

    private function validateBodyTargets(): void
    {
        $currentBodyFat = $this->resolveCurrentBodyFat(app(BodyMetricCalculator::class));

        $validator = validator(
            [
                'target_weight_kg' => $this->target_weight_kg,
                'target_body_fat_percent' => $this->target_body_fat_percent,
                'weight_kg' => $this->weight_kg,
            ],
            [
                'target_weight_kg' => [
                    'required',
                    'numeric',
                    'min:30',
                    'max:300',
                    Rule::when(
                        $this->goal_type === GoalType::WeightLoss->value,
                        'lt:weight_kg',
                    ),
                    Rule::when(
                        $this->goal_type === GoalType::MuscleGain->value,
                        'gt:weight_kg',
                    ),
                ],
                'target_body_fat_percent' => [
                    'required',
                    'numeric',
                    'min:3',
                    'max:70',
                ],
            ],
            [
                'target_weight_kg.required' => 'Indique le poids que tu vises.',
                'target_weight_kg.lt' => 'Pour une perte de poids, le poids cible doit être inférieur à ton poids actuel.',
                'target_weight_kg.gt' => 'Pour une prise de masse, le poids cible doit être supérieur à ton poids actuel.',
                'target_body_fat_percent.required' => 'Indique le % de graisse que tu vises.',
            ],
        );

        $validator->after(function ($validator) use ($currentBodyFat) {
            if ($currentBodyFat === null) {
                return;
            }

            if ($this->goal_type === GoalType::WeightLoss->value && $this->target_body_fat_percent >= $currentBodyFat) {
                $validator->errors()->add(
                    'target_body_fat_percent',
                    'Pour une perte de graisse, le % cible doit être inférieur à ton niveau actuel.',
                );
            }

            if ($this->goal_type === GoalType::MuscleGain->value && $this->target_body_fat_percent > $currentBodyFat + 5) {
                $validator->errors()->add(
                    'target_body_fat_percent',
                    'Pour une prise de masse maigre, le % cible ne devrait pas dépasser largement ton niveau actuel.',
                );
            }
        });

        $validator->validate();
    }

    private function resolveCurrentBodyFat(BodyMetricCalculator $calculator): ?float
    {
        $source = BodyMetricSource::tryFrom($this->metric_source) ?? BodyMetricSource::Manual;

        return $calculator->resolveBodyFat(
            $source,
            Gender::from($this->gender),
            (float) ($this->height_cm ?? 0),
            $this->body_fat_percent,
            $this->neck_cm,
            $this->waist_cm,
            $this->hip_cm,
        );
    }
}
