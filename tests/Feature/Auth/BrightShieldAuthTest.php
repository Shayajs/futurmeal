<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\BrightShieldAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Tests\TestCase;

class BrightShieldAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_creates_user_from_brightshield_profile(): void
    {
        $socialiteUser = $this->fakeSocialiteUser([
            'id' => '42',
            'email' => 'new@example.test',
            'name' => 'Nouveau User',
        ]);

        $user = app(BrightShieldAuthService::class)->resolveUser($socialiteUser);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new@example.test',
            'brightshell_id' => 42,
        ]);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->hasCompletedOnboarding());
    }

    public function test_service_links_existing_user_by_email(): void
    {
        $existing = User::factory()->create([
            'email' => 'existing@example.test',
            'brightshell_id' => null,
        ]);

        $socialiteUser = $this->fakeSocialiteUser([
            'id' => '99',
            'email' => 'existing@example.test',
            'name' => 'Existing User',
        ]);

        $user = app(BrightShieldAuthService::class)->resolveUser($socialiteUser);

        $this->assertSame($existing->id, $user->id);
        $this->assertSame(99, $user->brightshell_id);
        $this->assertNotNull($user->brightshield_linked_at);
    }

    public function test_service_logs_in_existing_linked_user(): void
    {
        $existing = User::factory()->create([
            'email' => 'linked@example.test',
            'brightshell_id' => 7,
            'brightshield_linked_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $socialiteUser = $this->fakeSocialiteUser([
            'id' => '7',
            'email' => 'linked@example.test',
            'name' => 'Linked User',
        ]);

        $user = app(BrightShieldAuthService::class)->resolveUser($socialiteUser);

        $this->assertSame($existing->id, $user->id);
        $this->assertSame(route('dashboard', absolute: false), app(BrightShieldAuthService::class)->redirectPath($user));
    }

    public function test_classic_login_still_works(): void
    {
        $user = User::factory()->create([
            'email' => 'classic@example.test',
            'onboarding_completed_at' => now(),
        ]);

        $this->post('/login', [
            'email' => 'classic@example.test',
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    /**
     * @param  array{id: string, email: string, name: string}  $data
     */
    private function fakeSocialiteUser(array $data): SocialiteUser
    {
        return new class($data) implements SocialiteUser
        {
            public function __construct(private array $data) {}

            public function getId()
            {
                return $this->data['id'];
            }

            public function getNickname()
            {
                return null;
            }

            public function getName()
            {
                return $this->data['name'];
            }

            public function getEmail()
            {
                return $this->data['email'];
            }

            public function getAvatar()
            {
                return null;
            }

            public function setToken($token)
            {
                return $this;
            }

            public function setRefreshToken($refreshToken)
            {
                return $this;
            }

            public function setExpiresIn($expiresIn)
            {
                return $this;
            }

            public function getRaw()
            {
                return $this->data;
            }
        };
    }
}
