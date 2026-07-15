<?php

namespace Tests\Unit;

use App\Models\Friendship;
use App\Models\User;
use App\Services\Social\FriendshipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FriendshipTest extends TestCase
{
    use RefreshDatabase;

    private FriendshipService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FriendshipService::class);
    }

    public function test_users_get_a_friend_code_on_creation(): void
    {
        $user = User::factory()->create();

        $this->assertNotEmpty($user->friend_code);
        $this->assertSame(8, strlen($user->friend_code));
    }

    public function test_send_request_creates_pending_friendship(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();

        $friendship = $this->service->sendRequest($alice, $bob);

        $this->assertNotNull($friendship);
        $this->assertSame(Friendship::STATUS_PENDING, $friendship->status);
        $this->assertFalse($this->service->areFriends($alice, $bob));
    }

    public function test_accept_makes_users_friends_both_ways(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();

        $friendship = $this->service->sendRequest($alice, $bob);
        $this->service->accept($bob, $friendship->id);

        $this->assertTrue($this->service->areFriends($alice, $bob));
        $this->assertTrue($this->service->areFriends($bob, $alice));
        $this->assertCount(1, $this->service->friendsOf($alice));
        $this->assertCount(1, $this->service->friendsOf($bob));
    }

    public function test_cannot_send_duplicate_or_reverse_request(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();

        $this->assertNotNull($this->service->sendRequest($alice, $bob));
        $this->assertNull($this->service->sendRequest($alice, $bob));
        $this->assertNull($this->service->sendRequest($bob, $alice));

        $this->assertSame(1, Friendship::count());
    }

    public function test_cannot_befriend_self(): void
    {
        $alice = User::factory()->create();

        $this->assertNull($this->service->sendRequest($alice, $alice));
    }

    public function test_only_recipient_can_accept(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();

        $friendship = $this->service->sendRequest($alice, $bob);
        $this->service->accept($alice, $friendship->id);

        $this->assertSame(Friendship::STATUS_PENDING, $friendship->fresh()->status);
    }

    public function test_remove_deletes_friendship_for_either_side(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();

        $friendship = $this->service->sendRequest($alice, $bob);
        $this->service->accept($bob, $friendship->id);
        $this->service->remove($alice, $friendship->id);

        $this->assertSame(0, Friendship::count());
        $this->assertFalse($this->service->areFriends($alice, $bob));
    }
}
