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

    public const NEW_APARTMENT_WARNING = 'Girdiğiniz daire sayısı mevcut ücretli aboneliğinizin kapsamını aşıyor. Devam ederseniz mevcut ücretli apartmanınız ve aboneliğiniz korunacak; girdiğiniz daire sayısıyla ayrı bir ücretsiz apartman oluşturulacak. Mevcut ücretli kullanım hakkınız yeni apartmana aktarılmayacak.';

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
        $covering = SubscriptionItem::query()
            ->where('apartment_id', $apartment->id)
            ->covering()
            ->first();

        if ($covering) {
            return [
                'active' => true,
                'floor' => (int) $covering->unit_count,
                'ceiling' => $this->ceiling($covering),
            ];
        }

        $lastPaid = SubscriptionItem::query()
            ->where('apartment_id', $apartment->id)
            ->where('plan', SubscriptionItem::PLAN_PAID)
            ->orderByDesc('expires_at')
            ->orderByDesc('id')
            ->first();

        if (! $lastPaid) {
            return null;
        }

        return [
            'active' => false,
            'floor' => $lastPaid->band_min_units !== null ? (int) $lastPaid->band_min_units : 1,
            'ceiling' => $this->ceiling($lastPaid),
        ];
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
