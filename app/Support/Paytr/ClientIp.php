<?php

namespace App\Support\Paytr;

use Illuminate\Validation\ValidationException;

class ClientIp
{
    public function resolve(string $requestIp, bool $isTest): string
    {
        $override = config('paytr.local_test_ip');
        $ip = app()->environment('local') && config('paytr.test_mode') && $isTest
            && is_string($override) && trim($override) !== '' ? trim($override) : $requestIp;

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw ValidationException::withMessages([
                'card_payment' => 'Ödeme için dış IP adresi doğrulanamadı. Yerel testte PAYTR_LOCAL_TEST_IP ayarını kontrol edin.',
            ]);
        }

        return $ip;
    }
}
