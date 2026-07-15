<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class BrightShieldAuthService
{
    public function resolveUser(SocialiteUser $socialiteUser): User
    {
        $brightshellId = (int) $socialiteUser->getId();
        $email = Str::lower(trim((string) $socialiteUser->getEmail()));

        $user = User::query()->where('brightshell_id', $brightshellId)->first();

        if ($user !== null) {
            $this->touchLink($user, $brightshellId);

            return $user;
        }

        if ($email !== '') {
            $user = User::query()->where('email', $email)->first();

            if ($user !== null) {
                $user->forceFill([
                    'brightshell_id' => $brightshellId,
                    'brightshield_linked_at' => now(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                return $user;
            }
        }

        $user = User::query()->create([
            'name' => $this->resolveDisplayName($socialiteUser, $email),
            'email' => $email,
            'password' => null,
            'email_verified_at' => now(),
            'brightshell_id' => $brightshellId,
            'brightshield_linked_at' => now(),
        ]);

        event(new Registered($user));

        return $user;
    }

    public function login(User $user): void
    {
        Auth::login($user, remember: true);
    }

    public function redirectPath(User $user): string
    {
        if (! $user->hasCompletedOnboarding()) {
            return route('onboarding', absolute: false);
        }

        return route('dashboard', absolute: false);
    }

    private function touchLink(User $user, int $brightshellId): void
    {
        if ($user->brightshell_id === $brightshellId && $user->brightshield_linked_at !== null) {
            return;
        }

        $user->forceFill([
            'brightshell_id' => $brightshellId,
            'brightshield_linked_at' => now(),
        ])->save();
    }

    private function resolveDisplayName(SocialiteUser $socialiteUser, string $email): string
    {
        $name = trim((string) $socialiteUser->getName());

        if ($name !== '') {
            return $name;
        }

        if ($email !== '') {
            return Str::before($email, '@');
        }

        return 'Utilisateur BrightShell';
    }
}
