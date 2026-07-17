<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'email_verified_at', 'onboarding_completed_at', 'friend_code', 'brightshell_id', 'brightshield_linked_at', 'ai_api_key', 'ai_api_base_url', 'ai_api_model'])]
#[Hidden(['password', 'remember_token', 'ai_api_key'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'brightshield_linked_at' => 'datetime',
            'password' => 'hashed',
            'ai_api_key' => 'encrypted',
        ];
    }

    public function hasAiApiConfigured(): bool
    {
        return filled($this->ai_api_key);
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->friend_code)) {
                $user->friend_code = strtoupper(Str::random(8));
            }
        });
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function bodyMetrics(): HasMany
    {
        return $this->hasMany(BodyMetric::class)->orderByDesc('recorded_at');
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function mealPlans(): HasMany
    {
        return $this->hasMany(MealPlan::class);
    }

    public function ownedPrograms(): HasMany
    {
        return $this->hasMany(Program::class, 'owner_id');
    }

    public function programMemberships(): HasMany
    {
        return $this->hasMany(ProgramMember::class);
    }

    public function budgetEntries(): HasMany
    {
        return $this->hasMany(BudgetEntry::class);
    }

    public function shoppingLists(): HasMany
    {
        return $this->hasMany(ShoppingList::class);
    }

    public function sentFriendships(): HasMany
    {
        return $this->hasMany(Friendship::class);
    }

    public function receivedFriendships(): HasMany
    {
        return $this->hasMany(Friendship::class, 'friend_id');
    }

    public function publishedMenus(): HasMany
    {
        return $this->hasMany(PublishedMenu::class);
    }

    public function ownedPlanShares(): HasMany
    {
        return $this->hasMany(PlanShare::class, 'owner_id');
    }

    public function viewingPlanShares(): HasMany
    {
        return $this->hasMany(PlanShare::class, 'viewer_id');
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed_at !== null;
    }
}
