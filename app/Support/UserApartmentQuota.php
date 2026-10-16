<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\User;
use App\Models\UserQuotaOverride;
use Illuminate\Validation\ValidationException;

class UserApartmentQuota
{
    public const LIMIT_MESSAGE = 'Tanımlı apartman oluşturma limitinize ulaştınız. Daha fazla apartman yönetimi için site yönetimi lisansı hakkında AidatCep ile iletişime geçin.';

    public const CONTACT_MESSAGE = 'Yönetim hakkınız dolu. Başka bir apartmanın yöneticisi olmak için AidatCep ile iletişime geçin.';

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
        return $this->ownerMembershipCount($user);
    }

    public function hasRoomForManagement(User $user): bool
    {
        if ($user->isAdminPanelUser()) {
            return true;
        }

        return $this->room($user, $this->definedLimit($user), $this->currentCount($user));
    }

    public function canCreate(User $user): bool
    {
        if ($user->isAdminPanelUser()) {
            return true;
        }

        if (session('open_apartment_requested') && $this->hasRoomForManagement($user)) {
            return true;
        }

        $max = $this->definedLimit($user);

        if (! $user->isSubscriber()) {
            return $max !== null && $this->currentCount($user) < $max;
        }

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
        $locked = User::query()->whereKey($user->id)->lockForUpdate()->first();

        if (! $locked || $locked->isAdminPanelUser()) {
            return;
        }

        $override = UserQuotaOverride::query()
            ->where('user_id', $locked->id)
            ->lockForUpdate()
            ->first();

        $max = $override ? (int) $override->max_apartments : null;
        $active = $this->ownerMembershipCount($locked, true);
        $explicit = (bool) session('open_apartment_requested') && $this->room($locked, $max, $active);

        if (! $locked->isSubscriber() && ! $explicit) {
            if ($max === null || $active >= $max) {
                throw ValidationException::withMessages([
                    'name' => self::LIMIT_MESSAGE,
                ]);
            }

            return;
        }

        if ($max !== null && $active >= $max) {
            throw ValidationException::withMessages([
                'name' => self::LIMIT_MESSAGE,
            ]);
        }
    }

    private function room(User $user, ?int $max, int $count): bool
    {
        if ($user->isAdminPanelUser()) {
            return true;
        }

        if ($max === null) {
            return $user->isSubscriber() || $count === 0;
        }

        return $count < $max;
    }

    private function ownerMembershipCount(User $user, bool $lock = false): int
    {
        $query = Apartment::query()
            ->where('apartments.is_active', true)
            ->whereHas('members', function ($members) use ($user) {
                $members->where('users.id', $user->id)
                    ->where('apartment_user.role', 'owner')
                    ->where('apartment_user.is_active', true);
            });

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->count();
    }
}
