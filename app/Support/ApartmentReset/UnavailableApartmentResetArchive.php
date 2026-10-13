<?php

namespace App\Support\ApartmentReset;

use App\Models\ApartmentDataOperation;

/**
 * Geliştirme notu: geri yükleme ve 30 günlük saklama yazılmadı.
 * Bu sınıf yedek üretmez ve bilinçli olarak başarısız döner.
 * Başarı mesajında "yedek alındı" veya "30 gün saklanır" denmez.
 */
class UnavailableApartmentResetArchive implements ApartmentResetArchive
{
    public function capture(ApartmentDataOperation $operation): bool
    {
        return false;
    }
}
