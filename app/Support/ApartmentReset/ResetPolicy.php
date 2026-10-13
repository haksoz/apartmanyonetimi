<?php

namespace App\Support\ApartmentReset;

use App\Models\Apartment;
use App\Models\SubscriptionItem;
use App\Support\ApartmentCommercial;

class ResetPolicy
{
    public const SAME_APARTMENT = 'same_apartment';

    public const BLOCKED_DECREASE = 'blocked_decrease';

    public const NEW_FREE_APARTMENT = 'new_free_apartment';

    public const QUOTE = 'quote';

    public const NEW_APARTMENT_WARNING = 'Girdiğiniz daire sayısı mevcut ücretli dilimin dışında. Devam ederseniz girdiğiniz daire sayısıyla ücretsiz apartman açılacak. Mevcut ücretli kullanım hakkınız yeni apartmana aktarılmayacak. Ücretli süre devam ediyorsa bu süre müşteri ekranından kalkacak.';

    /**
     * @return array{decision: string, active: bool, floor: int|null, ceiling: int|null}
     */
    public function decide(Apartment $apartment, int $unitCount): array
    {
        if ($unitCount > ApartmentCommercial::FREE_UNIT_LIMIT) {
            return $this->result(self::QUOTE, false, null, null);
        }

        $limit = $this->commercialLimit($apartment);

        if ($limit === null) {
            return $this->result(self::SAME_APARTMENT, false, 1, ApartmentCommercial::FREE_UNIT_LIMIT);
        }

        $paid = $this->paidItem($apartment);
        if ($paid && $this->outsidePaidBand($paid, $unitCount)) {
            return $this->result(self::NEW_FREE_APARTMENT, $limit['active'], $limit['floor'], $limit['ceiling']);
        }

        if ($unitCount > $limit['ceiling']) {
            return $this->result(self::NEW_FREE_APARTMENT, $limit['active'], $limit['floor'], $limit['ceiling']);
        }

        if ($unitCount < $limit['floor']) {
            return $this->result(self::BLOCKED_DECREASE, $limit['active'], $limit['floor'], $limit['ceiling']);
        }

        return $this->result(self::SAME_APARTMENT, $limit['active'], $limit['floor'], $limit['ceiling']);
    }

    /**
     * @return array{active: bool, floor: int, ceiling: int}|null
     */
    public function commercialLimit(Apartment $apartment): ?array
    {
        $covering = $this->paidItem($apartment);

        if ($covering && $this->isCoveringItem($covering)) {
            return [
                'active' => true,
                'floor' => $covering->band_min_units !== null ? (int) $covering->band_min_units : 1,
                'ceiling' => $this->ceiling($covering),
            ];
        }

        $lastPaid = $covering;

        if (! $lastPaid) {
            return null;
        }

        return [
            'active' => false,
            'floor' => $lastPaid->band_min_units !== null ? (int) $lastPaid->band_min_units : 1,
            'ceiling' => $this->ceiling($lastPaid),
        ];
    }

    private function paidItem(Apartment $apartment): ?SubscriptionItem
    {
        $covering = SubscriptionItem::query()
            ->where('apartment_id', $apartment->id)
            ->covering()
            ->first();

        if ($covering) {
            return $covering;
        }

        return SubscriptionItem::query()
            ->where('apartment_id', $apartment->id)
            ->where('plan', SubscriptionItem::PLAN_PAID)
            ->orderByDesc('expires_at')
            ->orderByDesc('id')
            ->first();
    }

    private function isCoveringItem(SubscriptionItem $item): bool
    {
        return SubscriptionItem::query()->whereKey($item->id)->covering()->exists();
    }

    private function outsidePaidBand(SubscriptionItem $item, int $unitCount): bool
    {
        $min = $item->band_min_units !== null ? (int) $item->band_min_units : 1;

        if ($unitCount < $min) {
            return true;
        }

        if ($item->band_max_units === null) {
            return false;
        }

        return $unitCount > (int) $item->band_max_units;
    }

    private function ceiling(SubscriptionItem $item): int
    {
        $bandMax = $item->band_max_units !== null ? (int) $item->band_max_units : (int) $item->unit_count;

        return min($bandMax, ApartmentCommercial::FREE_UNIT_LIMIT);
    }

    /**
     * @return array{decision: string, active: bool, floor: int|null, ceiling: int|null}
     */
    private function result(string $decision, bool $active, ?int $floor, ?int $ceiling): array
    {
        return [
            'decision' => $decision,
            'active' => $active,
            'floor' => $floor,
            'ceiling' => $ceiling,
        ];
    }
}
