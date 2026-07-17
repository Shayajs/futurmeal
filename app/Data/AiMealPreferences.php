<?php

namespace App\Data;

use App\Enums\MealComplexity;
use App\Enums\WheyPreference;

readonly class AiMealPreferences
{
    /**
     * @param  list<string>  $forbiddenFoods
     * @param  list<string>  $preferredFoods
     */
    public function __construct(
        public WheyPreference $whey,
        public MealComplexity $mealComplexity,
        public array $forbiddenFoods,
        public array $preferredFoods,
        public int $tastyDays,
        public bool $includeDesserts = false,
        public string $freeInstructions = '',
    ) {}

    public static function fromProfile(?\App\Models\UserProfile $profile): self
    {
        return new self(
            whey: $profile?->ai_whey ?? WheyPreference::None,
            mealComplexity: $profile?->ai_meal_complexity ?? MealComplexity::SimpleBudget,
            forbiddenFoods: $profile?->ai_forbidden_foods ?? [],
            preferredFoods: $profile?->ai_preferred_foods ?? [],
            tastyDays: $profile?->ai_tasty_days_per_week ?? 1,
            includeDesserts: (bool) ($profile?->ai_include_desserts ?? false),
            freeInstructions: '',
        );
    }

    /**
     * @return array{whey: string, meal_complexity: string, forbidden_foods: list<string>, preferred_foods: list<string>, tasty_days: int, include_desserts: bool, free_instructions: string}
     */
    public function toArray(): array
    {
        return [
            'whey' => $this->whey->value,
            'meal_complexity' => $this->mealComplexity->value,
            'forbidden_foods' => $this->forbiddenFoods,
            'preferred_foods' => $this->preferredFoods,
            'tasty_days' => $this->tastyDays,
            'include_desserts' => $this->includeDesserts,
            'free_instructions' => $this->freeInstructions,
        ];
    }
}
