<?php

namespace App\Support;

use App\Models\OrderInvoice;
use Illuminate\Support\Collection;

class OrderInvoiceXmlMatching
{
    public function candidates(array $parsed): Collection
    {
        return OrderInvoice::query()->whereNull('invoice_number')
            ->with(['order.subscription', 'order.items.apartmentSubscription'])
            ->orderByDesc('id')->get()
            ->filter(fn (OrderInvoice $invoice) => $this->matches($invoice, $parsed))->values();
    }

    public function matches(OrderInvoice $invoice, array $parsed): bool
    {
        if (filled($invoice->invoice_number)) {
            return false;
        }
        $snapshot = $invoice->snapshot;
        $identity = preg_replace('/\s+/u', '', (string) ($snapshot['billing']['billing_identity_number'] ?? ''));
        if ($identity === '' || $identity !== $parsed['customer_identity']) {
            return false;
        }
        if ($parsed['order_numbers'] !== [] && ! in_array(strtoupper((string) $snapshot['order_number']), $parsed['order_numbers'], true)) {
            return false;
        }
        if ($parsed['subscription_numbers'] !== []) {
            $numbers = $snapshot['subscription_numbers'] ?? [];
            foreach ($snapshot['lines'] as $line) {
                preg_match_all('/\bABN-\d{2}-[A-Z0-9]+(?:-[A-Z0-9]+)*\b/i', $line['description'], $found);
                $numbers = array_merge($numbers, $found[0]);
            }
            // Older tracking records may predate the structured subscription references.
            if ($numbers === [] && $invoice->order) {
                $numbers = $invoice->order->items->pluck('apartmentSubscription.subscription_no')->filter()->all();
                if ($invoice->order->subscription?->subscription_no) {
                    $numbers[] = $invoice->order->subscription->subscription_no;
                }
            }
            if (array_intersect($parsed['subscription_numbers'], array_map('strtoupper', $numbers)) === []) {
                return false;
            }
        }

        return true;
    }

    public function differences(OrderInvoice $invoice, array $parsed): array
    {
        $differences = [];
        foreach (['net_amount', 'tax_amount', 'amount'] as $key) {
            $differences[$key] = (int) round($parsed[$key] * 100) - (int) round($invoice->snapshot[$key] * 100);
        }

        return $differences;
    }
}
