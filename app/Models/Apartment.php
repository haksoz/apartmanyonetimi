<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Apartment extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'user_id',
        'name',
        'address',
        'province',
        'district',
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
        } while (static::withTrashed()->where('code', $code)->exists());

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
     * Create a new "Hesapsız" (orphan) account with TDR code for this apartment.
     * This account is used for expenses and payments without a specific account.
     * Each call creates a new account with a unique TDR code.
     */
    public function getOrphanAccount(): Account
    {
        $accountCode = $this->generateTdrCode();

        return $this->accounts()->create([
            'type' => Account::TYPE_SUPPLIER,
            'name' => $accountCode,
            'is_active' => true,
            'is_hidden' => true,
        ]);
    }

    private function generateTdrCode(): string
    {
        $apartmentCode = $this->code ?? 'XXXX';
        $year = \Carbon\Carbon::now()->format('y');

        $pattern = 'TDR-' . $apartmentCode . '-' . $year . '-%';

        $lastRef = $this->accounts()
            ->where('name', 'like', $pattern)
            ->withTrashed()
            ->orderBy('name', 'desc')
            ->value('name');

        if ($lastRef) {
            $lastNum = (int) substr($lastRef, strrpos($lastRef, '-') + 1);
            $nextNum = $lastNum + 1;
        } else {
            $nextNum = 1;
        }

        return sprintf('TDR-%s-%s-%05d', $apartmentCode, $year, $nextNum);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'is_active')
            ->withTimestamps();
    }
}
