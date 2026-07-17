<?php

namespace Tests\Feature;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use App\Enums\GoalType;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PagesSmokeTest extends TestCase
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
            'goal_type' => GoalType::WeightLoss,
            'planning_horizon_days' => 7,
            'daily_calorie_target' => 2000,
            'calorie_adjustment' => -400,
        ]);

        return $user;
    }

    public function test_all_main_pages_render(): void
    {
        $user = $this->onboardedUser();

        $pages = [
            '/dashboard',
            '/planner',
            '/planner/day/'.today()->toDateString(),
            '/charts',
            '/friends',
            '/discover',
            '/notifications',
            '/settings',
            '/settings/nutrition',
            '/settings/budget',
            '/settings/ai',
            '/profile',
        ];

        foreach ($pages as $page) {
            $this->actingAs($user)->get($page)->assertOk();
        }
    }
}
