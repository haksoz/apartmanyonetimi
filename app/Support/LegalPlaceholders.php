<?php

namespace App\Support;

use App\Models\UserSubscription;
use InvalidArgumentException;

class LegalPlaceholders
{
    public const BLANK = 'Belirtilmedi';

    public const PENDING_START = 'Ödeme onay tarihi itibarıyla';

    public const PENDING_END = 'Ödeme onay tarihinden itibaren seçilen hizmet dönemi';

    public const PREVIEW_ORDER_NUMBER = 'Sipariş oluşturulduğunda verilir';

    public const KEYS = [
        'apartment_name',
        'unit_count',
        'period',
        'price',
        'payment_method',
        'order_number',
        'billing_legal_name',
        'billing_identity_number',
        'billing_tax_office',
        'billing_address',
        'billing_email',
        'billing_phone',
        'service_start',
        'service_end',
    ];

    public const PUBLIC_LABELS = [
        'apartment_name' => 'Siparişte seçilen apartman',
        'unit_count' => 'Siparişteki daire sayısı',
        'period' => 'Aylık veya 12 aylık',
        'price' => 'Siparişte gösterilen vergiler dahil toplam bedel',
        'payment_method' => 'Siparişte seçilen ödeme yöntemi',
        'order_number' => 'Sipariş oluşturulduğunda verilen numara',
        'billing_legal_name' => 'Fatura alıcısı / unvan',
        'billing_identity_number' => 'T.C. kimlik numarası veya vergi numarası',
        'billing_tax_office' => 'Vergi dairesi',
        'billing_address' => 'Fatura adresi',
        'billing_email' => 'Fatura e-postası',
        'billing_phone' => 'Fatura telefonu',
        'service_start' => self::PENDING_START,
        'service_end' => self::PENDING_END,
    ];

    /**
     * Sipariş satırındaki dondurulmuş alanlar. Güncel profil ve kullanıcı kaydı okunmaz.
     *
     * @return array<string, string>
     */
    public static function fromOrder(UserSubscription $order): array
    {
        $items = $order->items()->orderBy('id')->get(['apartment_name', 'unit_count']);
        $confirmed = $order->status === UserSubscription::STATUS_ACTIVE
            && $order->started_at !== null
            && $order->expires_at !== null;

        return [
            'apartment_name' => self::join($items->pluck('apartment_name')->all()),
            'unit_count' => self::join($items->pluck('unit_count')->map(fn ($count) => (string) $count)->all()),
            'period' => self::periodLabel($order->period),
            'price' => self::money($order->price),
            'payment_method' => self::paymentLabel($order->payment_method),
            'order_number' => self::text($order->order_number),
            'billing_legal_name' => self::text($order->billing_legal_name),
            'billing_identity_number' => self::text($order->billing_identity_number),
            'billing_tax_office' => self::text($order->billing_tax_office),
            'billing_address' => self::address([
                $order->billing_address,
                $order->billing_district,
                $order->billing_province,
                $order->billing_postal_code,
                $order->billing_country,
            ]),
            'billing_email' => self::text($order->billing_email),
            'billing_phone' => self::text($order->billing_phone),
            'service_start' => $confirmed
                ? $order->started_at->timezone(config('app.timezone'))->format('d.m.Y')
                : self::PENDING_START,
            'service_end' => $confirmed
                ? $order->expires_at->timezone(config('app.timezone'))->format('d.m.Y')
                : self::PENDING_END,
        ];
    }

    /**
     * Sipariş henüz yokken form seçiminden önizleme. Sipariş numarası ve hizmet tarihleri kesinleşmez.
     *
     * @param  array<string, mixed>  $source
     * @return array<string, string>
     */
    public static function preview(array $source): array
    {
        return [
            'apartment_name' => self::text($source['apartment_name'] ?? null),
            'unit_count' => self::text(isset($source['unit_count']) ? (string) $source['unit_count'] : null),
            'period' => self::periodLabel($source['period'] ?? null),
            'price' => self::money($source['price'] ?? null),
            'payment_method' => self::paymentLabel($source['payment_method'] ?? null),
            'order_number' => self::PREVIEW_ORDER_NUMBER,
            'billing_legal_name' => self::text($source['billing_legal_name'] ?? null),
            'billing_identity_number' => self::text($source['billing_identity_number'] ?? null),
            'billing_tax_office' => self::text($source['billing_tax_office'] ?? null),
            'billing_address' => self::address([
                $source['billing_address'] ?? null,
                $source['billing_district'] ?? null,
                $source['billing_province'] ?? null,
                $source['billing_postal_code'] ?? null,
                $source['billing_country'] ?? null,
            ]),
            'billing_email' => self::text($source['billing_email'] ?? null),
            'billing_phone' => self::text($source['billing_phone'] ?? null),
            'service_start' => self::PENDING_START,
            'service_end' => self::PENDING_END,
        ];
    }

    /**
     * @param  array<string, string>  $values
     */
    public static function render(string $template, array $values): string
    {
        return self::walk($template, self::resolved($template, $values), false);
    }

    public static function html(string $template, ?array $values, bool $tokens = false): string
    {
        $resolved = $values === null
            ? self::resolved($template, self::PUBLIC_LABELS)
            : self::resolved($template, $values);

        return self::walk($template, $resolved, true, $tokens);
    }

    /**
     * @param  array<string, string>  $values
     * @return array<string, string>
     */
    private static function resolved(string $template, array $values): array
    {
        preg_match_all('/\{\{([^}]*)\}\}/', $template, $matches);
        $resolved = [];

        foreach ($matches[1] as $raw) {
            $key = trim($raw);
            if (! in_array($key, self::KEYS, true) || $key !== $raw) {
                throw new InvalidArgumentException('Bilinmeyen sözleşme alanı: '.$raw);
            }
            if (! array_key_exists($key, $values)) {
                throw new InvalidArgumentException('Sözleşme alanı için değer yok: '.$key);
            }
            $resolved[$raw] = (string) $values[$key];
        }

        return $resolved;
    }

    /**
     * @param  array<string, string>  $resolved
     */
    private static function walk(string $template, array $resolved, bool $escape, bool $tokens = false): string
    {
        $parts = preg_split('/\{\{([^}]*)\}\}/', $template, -1, PREG_SPLIT_DELIM_CAPTURE);
        $output = '';

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0) {
                $output .= $escape ? e($part) : $part;
                continue;
            }

            $text = $resolved[$part];
            $safe = $escape ? e($text) : $text;
            if ($escape && $tokens) {
                $output .= '<span data-legal-token="'.e(trim($part)).'">'.$safe.'</span>';
                continue;
            }
            $output .= $safe;
        }

        return $output;
    }

    private static function text(mixed $value): string
    {
        return filled($value) ? (string) $value : self::BLANK;
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    private static function join(array $parts): string
    {
        $values = array_values(array_filter($parts, fn ($value) => filled($value)));

        return $values === [] ? self::BLANK : implode(', ', $values);
    }

    /**
     * @param  array<int, mixed>  $parts
     */
    private static function address(array $parts): string
    {
        return self::join($parts);
    }

    private static function periodLabel(mixed $period): string
    {
        return match ($period) {
            'monthly' => 'Aylık',
            'yearly' => '12 aylık',
            default => self::text($period),
        };
    }

    private static function paymentLabel(mixed $method): string
    {
        return match ($method) {
            'havale' => 'Havale / EFT',
            'kredi_kartı' => 'Kredi kartı',
            default => self::text($method),
        };
    }

    private static function money(mixed $amount): string
    {
        if (! is_numeric($amount)) {
            return self::BLANK;
        }

        return number_format((float) $amount, 2, ',', '.').' ₺';
    }
}
