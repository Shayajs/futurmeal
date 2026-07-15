<?php

namespace Tests\Feature;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use App\Enums\GoalType;
use App\Enums\ProgramMemberRole;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Program\ProgramInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramInviteFeatureTest extends TestCase
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

    public function test_user_can_join_program_via_invite_link(): void
    {
        $owner = $this->onboardedUser();
        $guest = $this->onboardedUser();

        $program = Program::create([
            'owner_id' => $owner->id,
            'name' => 'Couple cut',
            'week_starts_on' => now()->startOfWeek(),
        ]);

        ProgramMember::create([
            'program_id' => $program->id,
            'user_id' => $owner->id,
            'role' => ProgramMemberRole::Owner,
        ]);

        $invitation = app(ProgramInvitationService::class)->createLink($program);

        $response = $this->actingAs($guest)->get(route('programs.join', ['token' => $invitation->token]));

        $response->assertRedirect(route('programs'));
        $this->assertDatabaseHas('program_members', [
            'program_id' => $program->id,
            'user_id' => $guest->id,
            'role' => ProgramMemberRole::Member->value,
        ]);
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }
}
