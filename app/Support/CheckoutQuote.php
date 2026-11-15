<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\User;

class CheckoutQuote
{
    public function __construct(private PriceQuote $prices) {}

    public function signature(User $user, Apartment $apartment): string
    {
        $values = [$user->id, $apartment->id, $apartment->unit_count, 'TRY'];
        foreach (['monthly', 'yearly'] as $period) {
            $quote = $this->prices->forUnits((int) $apartment->unit_count, $period, $apartment);
            $values[] = [$quote['amount'], $quote['net_amount'], $quote['tax_amount'], $quote['tax_rate'], $quote['campaign_name'] ?? null];
        }
        foreach (LegalConsent::SALE_DOCUMENTS as $key) {
            $document = \App\Models\LegalDocumentVersion::current($key);
            $values[] = [$key, $document?->id, $document?->version, $document ? hash('sha256', $document->body) : null];
        }

        return hash_hmac('sha256', json_encode($values, JSON_THROW_ON_ERROR), config('app.key'));
    }
}
