<?php

namespace App\Support;

use App\Models\SubscriptionItem;
use App\Models\UserSubscription;
use Illuminate\Validation\ValidationException;

class OrderInvoicing
{
    public function eligible(UserSubscription $order): bool
    {
        $paid = array_key_exists('collected_total', $order->getAttributes())
            ? $order->collected_total
            : $order->payments()->sum('amount');
        $review = array_key_exists('has_payment_review', $order->getAttributes())
            ? (bool) $order->has_payment_review
            : $order->paymentAttempts()->where('review_required', true)->exists();

        return ! $order->trashed()
            && $order->status === UserSubscription::STATUS_ACTIVE
            && $this->cents($order->price) > 0
            && $this->cents($paid) >= $this->cents($order->price)
            && ! $review;
    }

    public function snapshot(UserSubscription $order): array
    {
        $order->loadMissing(['user', 'items.apartmentSubscription']);
        $lines = $order->items
            ->map(fn (SubscriptionItem $item) => $this->line($order->serviceDescription($item), $item))
            ->all();

        if ($lines === []) {
            $lines[] = $this->line($order->serviceDescription(), $order);
        }

        if (array_sum(array_map(fn ($line) => $this->cents($line['amount']), $lines)) !== $this->cents($order->price)) {
            throw ValidationException::withMessages(['invoicing' => 'Sipariş kalemlerinin toplamı sipariş tutarıyla uyuşmuyor. Faturalamadan önce tutarları kontrol edin.']);
        }

        return [
            'order_number' => $order->order_number,
            'subscription_numbers' => $order->items->pluck('apartmentSubscription.subscription_no')
                ->push($order->subscription?->subscription_no)->filter()->unique()->values()->all(),
            'payer_name' => $order->user?->name,
            'billing' => collect($order->getAttributes())->filter(fn ($value, $key) => str_starts_with($key, 'billing_') && $key !== 'billing_profile_id')->all(),
            'lines' => $lines,
            'net_amount' => round(array_sum(array_column($lines, 'net_amount')), 2),
            'tax_amount' => round(array_sum(array_column($lines, 'tax_amount')), 2),
            'amount' => (float) $order->price,
            'paid_amount' => (float) $order->payments()->sum('amount'),
        ];
    }

    private function line(string $description, SubscriptionItem|UserSubscription $source): array
    {
        $amount = (float) ($source instanceof SubscriptionItem ? $source->amount : $source->price);
        $tax = (float) $source->tax_amount;
        $net = $source->net_amount !== null ? (float) $source->net_amount : round($amount - $tax, 2);
        $rate = (float) ($source->tax_rate ?? 0);
        if ($net < 0 || $tax < 0 || $rate < 0 || $this->cents($net) + $this->cents($tax) !== $this->cents($amount)
            || $this->cents($net * $rate / 100) !== $this->cents($tax)) {
            throw ValidationException::withMessages(['invoicing' => 'Kalemlerin KDV ve toplam tutarları tutarlı değil. Faturalamadan önce tutarları kontrol edin.']);
        }

        return ['description' => $description, 'net_amount' => $net, 'tax_rate' => $rate, 'tax_amount' => $tax, 'amount' => $amount];
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
