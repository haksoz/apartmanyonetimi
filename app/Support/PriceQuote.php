<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\PriceBand;
use App\Models\PriceCampaign;

class PriceQuote
{
    public function __construct(private SubscriptionTax $tax) {}

    public function forUnits(int $unitCount, string $period = 'monthly', ?Apartment $apartment = null): array
    {
        $band = $this->bandFor($unitCount);

        if (! $band) {
            return $this->quoteResult(null, null, true, 'Bu daire sayısı için tanımlı bir fiyat bandı yok.');
        }

        if ($unitCount > ApartmentCommercial::FREE_UNIT_LIMIT && ! $band->is_quote) {
            return $this->quoteResult($band, null, true, '101 ve üzeri daireli apartmanlar için özel fiyatlandırma uygulanır.');
        }

        if ($band->is_quote) {
            $custom = $period === 'yearly'
                ? $apartment?->custom_yearly_price
                : $apartment?->custom_monthly_price;

            if ($custom === null) {
                return $this->quoteResult($band, null, true, '151 ve üzeri daire için teklif fiyatı gerekir. Yönetici ile iletişime geçin.');
            }

            return $this->quoteResult($band, round((float) $custom, 2), false, null);
        }

        $list = $period === 'yearly' ? (float) $band->yearly_price : (float) $band->monthly_price;
        $priced = $this->applyCampaign($band, $list);

        $taxed = $this->tax->calculate($priced['amount']);
        $taxedList = $this->tax->calculate($priced['list_amount'], $taxed['tax_rate']);

        return [
            'band' => $band,
            ...$taxed,
            'list_amount' => $taxedList['amount'],
            'campaign_name' => $priced['campaign_name'],
            'discount_amount' => round($taxedList['amount'] - $taxed['amount'], 2),
            'requires_quote' => false,
            'message' => null,
        ];
    }

    public function bandFor(int $unitCount): ?PriceBand
    {
        $now = now();

        return PriceBand::query()
            ->where('is_active', true)
            ->where('min_units', '<=', $unitCount)
            ->where(function ($query) use ($unitCount) {
                $query->whereNull('max_units')->orWhere('max_units', '>=', $unitCount);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            })
            ->orderBy('sort_order')
            ->first();
    }

    private function quoteResult(?PriceBand $band, ?float $amount, bool $requiresQuote, ?string $message): array
    {
        $taxed = $amount === null ? [
            'net_amount' => null,
            'tax_rate' => $this->tax->rate(),
            'tax_amount' => null,
            'amount' => null,
        ] : $this->tax->calculate($amount);

        return [
            'band' => $band,
            ...$taxed,
            'list_amount' => $taxed['amount'],
            'campaign_name' => null,
            'discount_amount' => $amount === null ? null : 0.0,
            'requires_quote' => $requiresQuote,
            'message' => $message,
        ];
    }

    private function applyCampaign(PriceBand $band, float $list): array
    {
        $now = now();
        $amount = $list;

        $campaign = PriceCampaign::query()
            ->where('is_active', true)
            ->where(function ($query) use ($band) {
                $query->whereNull('price_band_id')->orWhere('price_band_id', $band->id);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->latest('id')
            ->first();

        if ($campaign?->percent_off !== null) {
            $amount -= $amount * ((float) $campaign->percent_off / 100);
        }

        if ($campaign?->amount_off !== null) {
            $amount -= (float) $campaign->amount_off;
        }

        $amount = round(max(0, $amount), 2);
        $list = round($list, 2);

        return [
            'amount' => $amount,
            'list_amount' => $list,
            'campaign_name' => $campaign?->name,
            'discount_amount' => round(max(0, $list - $amount), 2),
        ];
    }
}
