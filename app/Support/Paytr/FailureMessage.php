<?php

namespace App\Support\Paytr;

final class FailureMessage
{
    public static function forCode(?string $code): array
    {
        [$title, $message, $retry] = match ($code) {
            '1' => ['Doğrulama tamamlanmadı', 'Ödeme için gereken kimlik doğrulama adımı tamamlanmadı. Yeniden deneyerek doğrulamayı tamamlayabilirsiniz.', true],
            '2' => ['Doğrulama başarısız', 'Ödeme doğrulaması başarısız oldu. Yeniden deneyerek doğrulama bilgilerinizi kontrol edebilirsiniz.', true],
            '3' => ['Ödeme onaylanmadı', 'Ödeme güvenlik kontrolünden geçemedi veya kontrol tamamlanamadı. Yardım için destek ekibiyle iletişime geçin.', false],
            '6' => ['Ödeme tamamlanmadı', 'Ödeme süresi doldu veya ödeme ekranından çıkıldı. Yeni bir ödeme işlemi başlatabilirsiniz.', true],
            '8' => ['Taksit seçimi uygun değil', 'Seçilen taksit planı bu kart için kullanılamıyor. Yeniden deneyerek tek çekim veya uygun başka bir kart seçebilirsiniz.', true],
            '9' => ['Kartla ödeme onaylanmadı', 'Bu kart için işlem yetkisi bulunmuyor. Başka bir kartla deneyebilir veya bankanızla görüşebilirsiniz.', true],
            '10' => ['3D Secure doğrulaması gerekli', 'Bu ödeme 3D Secure doğrulamasıyla tamamlanmalıdır. Yardım için destek ekibiyle iletişime geçin.', false],
            '11' => ['Ödeme onaylanmadı', 'Ödeme güvenlik nedeniyle onaylanmadı. Devam etmek için destek ekibiyle iletişime geçin.', false],
            '99' => ['Ödeme tamamlanamadı', 'Teknik bir sorun nedeniyle ödeme tamamlanamadı. Yardım için destek ekibiyle iletişime geçin.', false],
            default => ['Ödeme tamamlanamadı', 'Ödeme onaylanmadı. Kart bilgilerinizi kontrol edebilir, başka bir kartla deneyebilir veya bankanızla görüşebilirsiniz.', true],
        };

        return compact('title', 'message', 'retry');
    }
}
