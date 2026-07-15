<?php

namespace Tests\Unit;

use App\Enums\ActivityLevel;
use App\Enums\Gender;
use App\Enums\GoalType;
use App\Models\Friendship;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\Plan\PlanViewContextService;
use App\Services\Social\PlanShareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PlanViewContextServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanViewContextService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->service = app(PlanViewContextService::class);
    }

    private function profile(User $user): void
    {
        UserProfile::create([
            'user_id' => $user->id,
            'gender' => Gender::Male,
            'birth_date' => '1990-01-01',
            'height_cm' => 180,
            'activity_level' => ActivityLevel::Moderate,
            'goal_type' => GoalType::WeightLoss,
            'planning_horizon_days' => 7,
            'daily_calorie_target' => 2000,
        ]);
    }

    public function test_resolve_self_by_default(): void
    {
        $user = User::factory()->create();
        $this->profile($user);

        $context = $this->service->resolve($user);

        $this->assertTrue($context->isSelf());
        $this->assertTrue($context->canEdit);
    }

    public function test_resolve_friend_when_share_accepted(): void
    {
        [$viewer, $owner] = User::factory()->count(2)->create();
        $this->profile($viewer);
        $this->profile($owner);
        Friendship::create(['user_id' => $viewer->id, 'friend_id' => $owner->id, 'status' => 'accepted']);

        $share = app(PlanShareService::class)->inviteToFollow($owner, $viewer, false);
        app(PlanShareService::class)->accept($viewer, $share->id);

        $context = $this->service->resolve($viewer, $owner->id);

        $this->assertTrue($context->isFriend());
        $this->assertSame($owner->id, $context->planOwner->id);
    }

    public function test_unauthorized_friend_falls_back_to_self(): void
    {
        [$viewer, $stranger] = User::factory()->count(2)->create();
        $this->profile($viewer);

        $context = $this->service->resolve($viewer, $stranger->id);

        $this->assertTrue($context->isSelf());
    }

    public function test_set_primary_persists_on_profile(): void
    {
        [$viewer, $owner] = User::factory()->count(2)->create();
        $this->profile($viewer);
        Friendship::create(['user_id' => $viewer->id, 'friend_id' => $owner->id, 'status' => 'accepted']);

        $share = app(PlanShareService::class)->inviteToFollow($owner, $viewer, false);
        app(PlanShareService::class)->accept($viewer, $share->id);

        $this->service->setPrimary($viewer, $owner->id);

        $this->assertSame($owner->id, $viewer->profile->fresh()->plan_view_user_id);
    }
}
