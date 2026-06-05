<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Apartment extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'address',
        'unit_count',
        'manager_unit_id',
        'is_active',
        'code',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($apartment) {
            if (empty($apartment->code)) {
                $apartment->code = static::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function managerUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'manager_unit_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Get or create the special "Hesapsız" (orphan) account for this apartment.
     * This account is used for expenses and payments without a specific account.
     */
    public function getOrphanAccount(): Account
    {
        $account = $this->accounts()
            ->where('type', Account::TYPE_SUPPLIER)
            ->where('name', 'Hesapsız')
            ->first();

        if (!$account) {
            $account = $this->accounts()->create([
                'type' => Account::TYPE_SUPPLIER,
                'name' => 'Hesapsız',
                'is_active' => true,
            ]);
        }

        return $account;
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'is_active')
            ->withTimestamps();
    }
}
