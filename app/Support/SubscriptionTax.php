<?php

namespace App\Support;

use App\Models\SystemSetting;

class SubscriptionTax
{
    public function rate(): float
    {
        return round((float) (SystemSetting::query()->value('subscription_tax_rate') ?? 20), 2);
    }

    public function calculate(float $netAmount, ?float $rate = null): array
    {
        $net = round($netAmount, 2);
        $rate ??= $this->rate();
        $tax = round($net * $rate / 100, 2);

        return [
            'net_amount' => $net,
            'tax_rate' => round($rate, 2),
            'tax_amount' => $tax,
            'amount' => round($net + $tax, 2),
        ];
    }
}
