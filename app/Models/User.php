<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Account;
use App\Models\Apartment;
use App\Models\BillingProfile;
use App\Models\UserSubscription;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'deactivated_at', 'activated_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements CanResetPasswordContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, CanResetPassword;

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_ACCOUNTANT = 'accountant';

    public const ROLE_SUPPORT = 'support';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_RESIDENT = 'resident';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'activated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function ownedApartments(): HasMany
    {
        return $this->hasMany(Apartment::class);
    }

    public function apartments(): BelongsToMany
    {
        return $this->belongsToMany(Apartment::class)
            ->withPivot('role', 'is_active')
            ->withTimestamps();
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function billingProfiles(): HasMany
    {
        return $this->hasMany(BillingProfile::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class)
            ->orderByDesc('started_at');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(UserSubscription::class)
            ->where('is_active', true)
            ->latest('started_at');
    }

    public function quotaOverride(): HasOne
    {
        return $this->hasOne(UserQuotaOverride::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    public static function adminRoles(): array
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_ADMIN,
            self::ROLE_ACCOUNTANT,
            self::ROLE_SUPPORT,
        ];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdminPanelUser(): bool
    {
        return in_array($this->role, self::adminRoles(), true);
    }

    public function isSubscriber(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isActivated(): bool
    {
        return $this->activated_at !== null;
    }

    public function managesBuilding(): bool
    {
        if (! $this->isActivated()) {
            return false;
        }

        return $this->apartments()
            ->where('apartments.is_active', true)
            ->wherePivot('role', 'owner')
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function canPurchaseSubscription(): bool
    {
        return $this->isActivated() && ($this->isSubscriber() || $this->managesBuilding());
    }

    public function hasFeature(string $key): bool
    {
        $subscription = $this->subscription;

        if (! $subscription) {
            return false;
        }

        return $subscription->hasFeature($key);
    }

}
