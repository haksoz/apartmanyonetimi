<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    public const TYPE_OWNER = 'owner';

    public const TYPE_TENANT = 'tenant';

    public const TYPE_SUPPLIER = 'supplier';

    protected $fillable = [
        'apartment_id',
        'user_id',
        'unit_id',
        'type',
        'name',
        'phone',
        'email',
        'balance',
        'account_opening_date',
        'account_end_date',
        'is_active',
        'is_hidden',
        'default_category_id',
        'portal_suspended_until',
        'portal_suspension_reason',
    ];

    protected $casts = [
        'account_opening_date' => 'date',
        'account_end_date' => 'date',
        'is_hidden' => 'boolean',
        'portal_suspended_until' => 'datetime',
    ];

    public function apartment(): BelongsTo
    {
        return $this->belongsTo(Apartment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function portalOffers(): HasMany
    {
        return $this->hasMany(AccountPortalOffer::class);
    }

    public function portalInvitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class);
    }

    public function portalAccessEvents(): HasMany
    {
        return $this->hasMany(AccountPortalAccessEvent::class);
    }

    public function portalIsSuspended(): bool
    {
        return $this->portal_suspended_until?->isFuture() ?? false;
    }

    public function residenceHasEnded(): bool
    {
        return ! $this->is_active || ($this->account_end_date && $this->account_end_date->lte(today()));
    }

    public function getResidenceStatusLabelAttribute(): string
    {
        if ($this->residenceHasEnded()) {
            return $this->type === self::TYPE_TENANT ? 'Kiracılığı sonlanmış' : 'Kat malikliği sonlanmış';
        }

        return $this->type_label;
    }

    public function getResidenceStartedAtAttribute(): ?Carbon
    {
        return $this->type === self::TYPE_TENANT
            ? ($this->tenantAssignments->sortByDesc('move_in_date')->first()?->move_in_date ?? $this->account_opening_date)
            : $this->account_opening_date;
    }

    protected static function booted(): void
    {
        static::updated(function (Account $account) {
            if ($account->wasChanged(['email', 'type'])) {
                $account->portalInvitations()->whereNull('accepted_at')->delete();
                $account->portalOffers()->where('status', AccountPortalOffer::STATUS_PENDING)
                    ->update(['status' => AccountPortalOffer::STATUS_REJECTED, 'rejected_at' => now()]);
            }
        });
    }

    public function defaultCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'default_category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AccountTransaction::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function dues(): HasMany
    {
        return $this->hasMany(Due::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function tenantAssignments(): HasMany
    {
        return $this->hasMany(TenantAssignment::class);
    }

    public function activeTenantAssignment()
    {
        return $this->hasOne(TenantAssignment::class)
            ->whereNull('move_out_date')
            ->latest('move_in_date')
            ->latest();
    }

    public function getActiveTenantAssignmentAttribute(): ?TenantAssignment
    {
        return $this->tenantAssignments()
            ->whereNull('move_out_date')
            ->latest('move_in_date')
            ->latest()
            ->first();
    }

    public function getLedgerDebitAttribute(): float
    {
        return (float) $this->transactions()->where('type', 'debit')->sum('amount');
    }

    public function getLedgerCreditAttribute(): float
    {
        return (float) $this->transactions()->where('type', 'credit')->sum('amount');
    }

    public function getLedgerBalanceAttribute(): float
    {
        return $this->ledger_credit - $this->ledger_debit;
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_OWNER => 'Kat Maliki',
            self::TYPE_TENANT => 'Kiracı',
            self::TYPE_SUPPLIER => 'Tedarikçi',
            default => ucfirst((string) $this->type),
        };
    }

    public function loginEmailIsLocked(): bool
    {
        $user = $this->user;

        if (! $user || ! $user->isActivated() || ! filled($this->email)) {
            return false;
        }

        return strcasecmp($this->email, $user->email) === 0;
    }
}
