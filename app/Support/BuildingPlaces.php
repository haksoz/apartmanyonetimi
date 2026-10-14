<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\User;
use Illuminate\Support\Collection;

class BuildingPlaces
{
    public function __construct(private CurrentApartment $currentApartment) {}

    /**
     * @return array{residences: Collection<int, Apartment>, managed: Collection<int, Apartment>}
     */
    public function grouped(User $user): array
    {
        $apartments = $this->currentApartment->availableFor($user);

        if ($user->isAdmin()) {
            return [
                'residences' => collect(),
                'managed' => $apartments,
            ];
        }

        $ids = $apartments->pluck('id');

        $managedIds = $user->apartments()
            ->whereIn('apartments.id', $ids)
            ->wherePivot('role', 'owner')
            ->wherePivot('is_active', true)
            ->pluck('apartments.id');

        $memberIds = $user->apartments()
            ->whereIn('apartments.id', $ids)
            ->wherePivot('role', 'member')
            ->wherePivot('is_active', true)
            ->pluck('apartments.id');

        $accountIds = Account::query()
            ->where('user_id', $user->id)
            ->whereIn('apartment_id', $ids)
            ->whereIn('type', [Account::TYPE_OWNER, Account::TYPE_TENANT])
            ->pluck('apartment_id');

        $residenceIds = $memberIds->merge($accountIds)->unique();

        return [
            'residences' => $apartments->whereIn('id', $residenceIds->all())->values(),
            'managed' => $apartments->whereIn('id', $managedIds->all())->values(),
        ];
    }

    public function hasResidenceIn(User $user, Apartment $apartment): bool
    {
        return Account::query()
            ->where('user_id', $user->id)
            ->where('apartment_id', $apartment->id)
            ->whereIn('type', [Account::TYPE_OWNER, Account::TYPE_TENANT])
            ->exists();
    }
}
