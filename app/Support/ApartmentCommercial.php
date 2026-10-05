<?php

namespace App\Support;

class ApartmentCommercial
{
    public const FREE_UNIT_LIMIT = 100;

    public function assess(int $unitCount, bool $wantsPaid): array
    {
        if ($unitCount >= 151 && ! $wantsPaid) {
            return $this->blocked('151 ve üzeri daireli apartman ücretsiz açılamaz. Teklif için yönetici ile iletişime geçin.');
        }

        if ($unitCount > self::FREE_UNIT_LIMIT && ! $wantsPaid) {
            return $this->blocked('101 ve üzeri daireli apartman ücretsiz açılamaz. Ücretli kullanımı seçin.');
        }

        if ($unitCount >= 151) {
            return $this->blocked('151 ve üzeri daire için önce teklif fiyatı tanımlanmalıdır.');
        }

        if ($wantsPaid || $unitCount > self::FREE_UNIT_LIMIT) {
            return [
                'allowed' => true,
                'plan' => 'paid',
                'message' => null,
            ];
        }

        return [
            'allowed' => true,
            'plan' => 'free',
            'message' => null,
        ];
    }

    private function blocked(string $message): array
    {
        return [
            'allowed' => false,
            'plan' => null,
            'message' => $message,
        ];
    }
}
