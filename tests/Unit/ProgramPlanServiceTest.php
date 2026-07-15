<?php

namespace Tests\Unit;

use App\Enums\ProgramMemberRole;
use App\Models\MealPlan;
use App\Models\Program;
use App\Models\ProgramMember;
use App\Models\User;
use App\Services\Program\ProgramPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProgramPlanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProgramPlanService::class);
    }

    public function test_owner_can_edit_locked_program(): void
    {
        $owner = User::factory()->create();
        $program = Program::create([
            'owner_id' => $owner->id,
            'name' => 'Cut',
            'lock_portions' => true,
            'week_starts_on' => now()->startOfWeek(),
        ]);

        ProgramMember::create([
            'program_id' => $program->id,
            'user_id' => $owner->id,
            'role' => ProgramMemberRole::Owner,
        ]);

        $this->assertTrue($this->service->canEditPlan($owner, $program));
    }

    public function test_member_cannot_edit_when_portions_locked(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $program = Program::create([
            'owner_id' => $owner->id,
            'name' => 'Cut',
            'lock_portions' => true,
            'week_starts_on' => now()->startOfWeek(),
        ]);

        ProgramMember::create([
            'program_id' => $program->id,
            'user_id' => $owner->id,
            'role' => ProgramMemberRole::Owner,
        ]);

        ProgramMember::create([
            'program_id' => $program->id,
            'user_id' => $member->id,
            'role' => ProgramMemberRole::Member,
        ]);

        $this->assertFalse($this->service->canEditPlan($member, $program));
    }

    public function test_member_can_edit_when_unlocked(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $program = Program::create([
            'owner_id' => $owner->id,
            'name' => 'Cut',
            'lock_portions' => false,
            'week_starts_on' => now()->startOfWeek(),
        ]);

        ProgramMember::create([
            'program_id' => $program->id,
            'user_id' => $owner->id,
            'role' => ProgramMemberRole::Owner,
        ]);

        ProgramMember::create([
            'program_id' => $program->id,
            'user_id' => $member->id,
            'role' => ProgramMemberRole::Member,
        ]);

        $this->assertTrue($this->service->canEditPlan($member, $program));
    }

    public function test_ensure_program_plan_creates_shared_plan(): void
    {
        $owner = User::factory()->create();
        $program = Program::create([
            'owner_id' => $owner->id,
            'name' => 'Famille',
            'week_starts_on' => now()->startOfWeek(),
        ]);

        $plan = $this->service->ensureProgramPlan($program, $owner);

        $this->assertInstanceOf(MealPlan::class, $plan);
        $this->assertEquals($program->id, $plan->program_id);
        $this->assertEquals($owner->id, $plan->user_id);
    }
}
