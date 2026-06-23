<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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
            'password' => 'hashed',
        ];
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

    public function hasFeature(string $key): bool
    {
        $subscription = $this->subscription;

        if (! $subscription || $subscription->isExpired()) {
            return false;
        }

        return $subscription->package->hasFeature($key);
    }

}
