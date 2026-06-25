<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CurrentApartment
{
    public const SESSION_KEY = 'current_apartment_id';

    public function availableFor(User $user): Collection
    {
        return $this->queryFor($user)
            ->orderBy('name')
            ->get();
    }

    public function getFor(User $user): ?Apartment
    {
        $apartments = $this->availableFor($user);

        if ($apartments->isEmpty()) {
            session()->forget(self::SESSION_KEY);

            return null;
        }

        $selectedId = session(self::SESSION_KEY);
        $selected = $selectedId ? $apartments->firstWhere('id', (int) $selectedId) : null;

        if (! $selected) {
            session()->forget(self::SESSION_KEY);
        }

        return $selected;
    }

    public function hasAvailableFor(User $user): bool
    {
        return $this->queryFor($user)->exists();
    }

    public function setFor(User $user, int $apartmentId): Apartment
    {
        $apartment = $this->queryFor($user)->whereKey($apartmentId)->firstOrFail();

        session([self::SESSION_KEY => $apartment->id]);

        return $apartment;
    }

    public function isSuspendedFor(User $user): bool
    {
        if ($user->isAdmin()) {
            return false;
        }

        return Apartment::query()
            ->whereHas('members', function ($query) use ($user) {
                $query->whereKey($user->id)->where('apartment_user.is_active', false);
            })
            ->exists();
    }

    public function queryFor(User $user): Builder
    {
        return Apartment::query()
            ->where('is_active', true)
            ->when(! $user->isAdmin(), function ($query) use ($user) {
                $query->whereHas('members', function ($query) use ($user) {
                    $query->whereKey($user->id)->where('apartment_user.is_active', true);
                });
            });
    }
}
