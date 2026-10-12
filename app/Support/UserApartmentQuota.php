<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\User;
use App\Models\UserQuotaOverride;
use Illuminate\Validation\ValidationException;

class UserApartmentQuota
{
    public const LIMIT_MESSAGE = 'Tanımlı apartman oluşturma limitinize ulaştınız. Daha fazla apartman yönetimi için site yönetimi lisansı hakkında AidatCep ile iletişime geçin.';

    /**
     * Tanımlı limit yoksa null döner. Bu, mevcut müşteriler için sınırsız demektir.
     * Paket aboneliğindeki eski limit kullanılmaz; siparişi olmayan kullanıcıyı keser.
     */
    public function definedLimit(User $user): ?int
    {
        $override = $user->quotaOverride;

        if (! $override) {
            return null;
        }

        return (int) $override->max_apartments;
    }

    public function currentCount(User $user): int
    {
        return Apartment::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->count();
    }

    public function canCreate(User $user): bool
    {
        $max = $this->definedLimit($user);

        if ($max === null) {
            return true;
        }

        return $this->currentCount($user) < $max;
    }

    /**
     * Çağıran işlemin transaction'ı içinde çalışmalıdır.
     * Kullanıcı satırı kilitlenir; eşzamanlı ikinci oluşturma limiti aşamaz.
     */
    public function assertCanCreate(User $user): void
    {
        User::query()->whereKey($user->id)->lockForUpdate()->first();

        $override = UserQuotaOverride::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        if (! $override) {
            return;
        }

        $active = Apartment::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->lockForUpdate()
            ->count();

        if ($active >= (int) $override->max_apartments) {
            throw ValidationException::withMessages([
                'name' => self::LIMIT_MESSAGE,
            ]);
        }
    }
}
