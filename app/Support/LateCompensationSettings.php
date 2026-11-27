<?php

namespace App\Support;

use App\Models\Apartment;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;

class LateCompensationSettings
{
    // Called inside the apartment update transaction while holding the apartment lock.
    public function save(Apartment $apartment, array $input, int $userId): void
    {
        $active = (bool) ($input['is_active'] ?? false);
        $rate = $input['monthly_rate'] ?? null;
        $latest = $apartment->lateCompensationPolicies()->latest('effective_from')->first();

        if ($latest === null && ! $active && $rate === null && empty($input['starts_on'])) {
            return;
        }

        if ($rate === null) {
            throw ValidationException::withMessages(['late_compensation.monthly_rate' => 'Aylık Gecikme Tazminatı oranını girin.']);
        }

        $today = now('Europe/Istanbul')->toDateString();
        if ($latest === null) {
            $effective = $input['starts_on'] ?? null;
            if ($effective === null || $effective < $today) {
                throw ValidationException::withMessages(['late_compensation.starts_on' => 'Başlangıç tarihi bugün veya daha ileri bir tarih olmalıdır.']);
            }
            $apartment->update(['late_compensation_starts_on' => $effective]);
        } else {
            $effective = $input['effective_from'] ?? null;
            if ($effective === null && $latest->is_active === $active && BigDecimal::of($rate)->isEqualTo($latest->monthly_rate)) {
                return;
            }

            $earliest = max($today, $apartment->late_compensation_starts_on?->toDateString() ?? $today);
            if ($effective === null || $effective < $earliest) {
                throw ValidationException::withMessages(['late_compensation.effective_from' => 'Geçerlilik tarihi bugünden ve uygulama başlangıcından önce olamaz.']);
            }

            if ($apartment->lateCompensationPolicies()->whereDate('effective_from', $effective)->exists()) {
                throw ValidationException::withMessages(['late_compensation.effective_from' => 'Bu tarih için bir Gecikme Tazminatı politikası zaten kayıtlı.']);
            }
        }

        $apartment->lateCompensationPolicies()->create([
            'is_active' => $active,
            'monthly_rate' => $rate,
            'effective_from' => $effective,
            'created_by' => $userId,
        ]);
    }
}
