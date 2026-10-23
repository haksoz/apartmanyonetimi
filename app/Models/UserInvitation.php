<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserInvitation extends Model
{
    protected $fillable = [
        'user_id',
        'account_id',
        'email',
        'token_hash',
        'expires_at',
        'accepted_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public static function issueForAccount(Account $account): string
    {
        $account->portalInvitations()->whereNull('accepted_at')->delete();
        $plain = Str::random(64);
        static::query()->create([
            'account_id' => $account->id,
            'email' => $account->email,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(7),
        ]);

        return $plain;
    }

    public static function issue(User $user): string
    {
        static::query()
            ->where('user_id', $user->id)
            ->whereNull('accepted_at')
            ->delete();

        $plain = Str::random(64);

        static::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(7),
        ]);

        return $plain;
    }

    public static function findValid(string $plain): ?self
    {
        $invitation = static::query()
            ->where('token_hash', hash('sha256', $plain))
            ->first();

        if (! $invitation || $invitation->accepted_at || $invitation->expires_at->isPast()) {
            return null;
        }

        if ($invitation->account_id) {
            $account = $invitation->account;
            if (! $account || $account->user_id || ! in_array($account->type, [Account::TYPE_OWNER, Account::TYPE_TENANT], true)
                || strcasecmp((string) $account->email, (string) $invitation->email) !== 0) {
                return null;
            }
        }

        return $invitation;
    }
}
