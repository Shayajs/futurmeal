<?php

namespace Tests\Unit;

use App\Enums\PlanShareStatus;
use App\Models\Friendship;
use App\Models\User;
use App\Services\Social\FriendshipService;
use App\Services\Social\PlanShareService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PlanShareServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanShareService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->service = app(PlanShareService::class);
    }

    private function befriend(User $a, User $b): void
    {
        Friendship::create(['user_id' => $a->id, 'friend_id' => $b->id, 'status' => 'accepted']);
    }

    public function test_viewer_can_request_to_follow(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        $this->befriend($alice, $bob);

        $share = $this->service->requestToFollow($alice, $bob);

        $this->assertNotNull($share);
        $this->assertSame(PlanShareStatus::Pending, $share->status);
        $this->assertFalse($share->can_edit);
    }

    public function test_owner_can_invite_with_edit_permission(): void
    {
        [$owner, $viewer] = User::factory()->count(2)->create();
        $this->befriend($owner, $viewer);

        $share = $this->service->inviteToFollow($owner, $viewer, true);

        $this->assertTrue($share->can_edit);
    }

    public function test_owner_accepts_request_with_edit_flag(): void
    {
        [$viewer, $owner] = User::factory()->count(2)->create();
        $this->befriend($viewer, $owner);

        $share = $this->service->requestToFollow($viewer, $owner);
        $this->service->accept($owner, $share->id, true);

        $this->assertTrue($this->service->canEditPlan($viewer, $owner));
    }

    public function test_viewer_cannot_edit_without_permission(): void
    {
        [$viewer, $owner] = User::factory()->count(2)->create();
        $this->befriend($viewer, $owner);

        $share = $this->service->requestToFollow($viewer, $owner);
        $this->service->accept($owner, $share->id, false);

        $this->assertTrue($this->service->canViewPlan($viewer, $owner));
        $this->assertFalse($this->service->canEditPlan($viewer, $owner));
    }

    public function test_duplicate_request_returns_null(): void
    {
        [$viewer, $owner] = User::factory()->count(2)->create();
        $this->befriend($viewer, $owner);

        $this->assertNotNull($this->service->requestToFollow($viewer, $owner));
        $this->assertNull($this->service->requestToFollow($viewer, $owner));
    }

    public function test_revoke_removes_access(): void
    {
        [$viewer, $owner] = User::factory()->count(2)->create();
        $this->befriend($viewer, $owner);

        $share = $this->service->inviteToFollow($owner, $viewer, false);
        $this->service->accept($viewer, $share->id);
        $this->service->revoke($owner, $share->id);

        $this->assertFalse($this->service->canViewPlan($viewer, $owner));
    }
}
