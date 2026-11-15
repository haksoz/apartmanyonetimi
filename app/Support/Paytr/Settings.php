<?php

namespace App\Support\Paytr;

use Illuminate\Validation\ValidationException;

class Settings
{
    public function assertReady(): void
    {
        foreach (['merchant_id', 'merchant_key', 'merchant_salt'] as $key) {
            if (! is_string(config('paytr.'.$key)) || trim(config('paytr.'.$key)) === '') {
                $this->reject('Kart ödeme bağlantısı henüz hazır değil. Lütfen daha sonra tekrar deneyin.');
            }
        }
        $this->publicOrigin();
    }

    public function publicOrigin(): string
    {
        $origin = rtrim((string) config('paytr.public_url'), '/');
        $url = parse_url($origin);
        $host = strtolower($url['host'] ?? '');
        if (! $url || ($url['scheme'] ?? '') !== 'https' || $host === '' || ! str_contains($host, '.')
            || preg_match('/\.(test|local|localhost|internal)$/D', $host)
            || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])
            || ! empty($url['path']) || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            $this->reject('Kart ödemesi için dışarıdan erişilebilir bir HTTPS adresi tanımlanmalıdır.');
        }

        return $origin;
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['card_payment' => $message]);
    }
}
