<?php

namespace App\Support;

class ApartmentCommercial
{
    public const FREE_UNIT_LIMIT = 100;

    public const QUOTE_MESSAGE = '101 ve üzeri daireli apartmanlar için özel fiyatlandırma uygulanmaktadır. Talebiniz alınmıştır. Temsilcimiz sizinle iletişime geçerek size özel teklifinizi paylaşacaktır.';

    public function assess(int $unitCount): array
    {
        if ($unitCount > self::FREE_UNIT_LIMIT) {
            return [
                'allowed' => false,
                'plan' => null,
                'quote' => true,
                'message' => self::QUOTE_MESSAGE,
            ];
        }

        return [
            'allowed' => true,
            'plan' => 'free',
            'quote' => false,
            'message' => null,
        ];
    }
}
