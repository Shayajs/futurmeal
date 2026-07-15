<?php

namespace App\Models;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use App\Enums\GoalType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id',
        'gender',
        'birth_date',
        'height_cm',
        'activity_level',
        'goal_type',
        'planning_horizon_days',
        'daily_calorie_target',
        'calorie_adjustment',
        'target_weight_kg',
        'target_body_fat_percent',
        'plan_view_user_id',
        'open_prices_location_id',
        'open_prices_location_label',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => 'date',
            'height_cm' => 'float',
            'activity_level' => ActivityLevel::class,
            'goal_type' => GoalType::class,
            'planning_horizon_days' => 'integer',
            'daily_calorie_target' => 'integer',
            'calorie_adjustment' => 'integer',
            'target_weight_kg' => 'float',
            'target_body_fat_percent' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function planViewUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'plan_view_user_id');
    }

    public function weightGoalProgress(?float $currentWeight, ?float $startingWeight): ?int
    {
        if ($this->target_weight_kg === null || $currentWeight === null || $startingWeight === null) {
            return null;
        }

        $target = (float) $this->target_weight_kg;

        $totalDelta = match ($this->goal_type) {
            GoalType::WeightLoss => $startingWeight - $target,
            GoalType::MuscleGain => $target - $startingWeight,
        };

        if ($totalDelta <= 0) {
            return 100;
        }

        $currentDelta = match ($this->goal_type) {
            GoalType::WeightLoss => $startingWeight - $currentWeight,
            GoalType::MuscleGain => $currentWeight - $startingWeight,
        };

        return (int) min(100, max(0, round(($currentDelta / $totalDelta) * 100)));
    }

    public function bodyFatGoalProgress(?float $currentBodyFat, ?float $startingBodyFat): ?int
    {
        if ($this->target_body_fat_percent === null || $currentBodyFat === null || $startingBodyFat === null) {
            return null;
        }

        $target = (float) $this->target_body_fat_percent;
        $totalDelta = abs($startingBodyFat - $target);

        if ($totalDelta <= 0) {
            return 100;
        }

        $currentDelta = abs($startingBodyFat - $currentBodyFat);

        return (int) min(100, max(0, round(($currentDelta / $totalDelta) * 100)));
    }
}
