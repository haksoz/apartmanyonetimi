<?php

namespace App\Support\ApartmentReset;

use App\Models\ApartmentDataOperation;

/**
 * Silmeden önce geri yüklenebilir bir arşiv üretmek için kapı.
 * Gerçek dosya yedeği ve 30 günlük saklama henüz yok.
 * capture() true dönmeden operasyonel silme başlamaz.
 */
interface ApartmentResetArchive
{
    public function capture(ApartmentDataOperation $operation): bool;
}
