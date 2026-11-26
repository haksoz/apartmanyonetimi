<?php

namespace App\Services;

use App\Enums\DueType;
use App\Models\Apartment;
use App\Models\ConsumptionPeriod;
use App\Models\Unit;
use App\Models\UnitConsumption;
use App\Support\AidatPeriodReconciliation;
use App\Support\DueTargeting;
use Brick\Math\BigInteger;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ConsumptionDistribution
{
    public function summary(Apartment $apartment, array $input, float $amount): array
    {
        $period = ConsumptionPeriod::where('apartment_id', $apartment->id)
            ->where('consumption_type', $input['consumption_type'])->findOrFail($input['consumption_period_id']);
        if ($period->batches()->whereNull('consumption_cancelled_at')->exists()) {
            throw ValidationException::withMessages(['consumption_period_id' => 'Bu okuma dönemi zaten borçlandırılmış.']);
        }
        $readings = UnitConsumption::where('apartment_id', $apartment->id)->where('consumption_period_id', $period->id)->get()->keyBy('unit_id');
        $units = Unit::where('apartment_id', $apartment->id)->with('ownerAccount')->orderBy('id')->get();
        $rows = [];
        $total = BigInteger::zero();
        $canConfirm = true;
        $date = Carbon::parse($input['period'].'-01');
        foreach ($units as $unit) {
            $reading = $readings->get($unit->id);
            $weight = $reading ? BigInteger::of((string) MeterReadingService::scaled($reading->consumption)) : BigInteger::zero();
            $error = null;
            $account = null;
            if (! $reading) {
                $error = 'Okuma eksik';
            } elseif ($reading->measurement_unit !== $period->measurement_unit || $reading->method !== $period->method) {
                $error = 'Ölçüm birimi veya yöntemi dönemle uyumlu değil';
            } else {
                try {
                    $account = app(DueTargeting::class)->accountFor($unit, $date, DueType::from($input['due_type']));
                } catch (ValidationException $exception) {
                    $error = collect($exception->errors())->flatten()->first();
                }
                if ($weight->isPositive() && ! $account) {
                    $error ??= 'Borçlandırılacak uygun hesap bulunamadı';
                }
                if ($weight->isPositive() && $account && $input['due_type'] === 'aidat'
                    && (int) $input['category_id'] === (int) $apartment->default_aidat_category_id
                    && app(AidatPeriodReconciliation::class)->hasExistingAidatDue($apartment, $account->id, $input['period'])) {
                    $error = 'Bu hesap için aynı ayda Genel Aidat mevcut; uygun başka bir kategori seçin';
                }
            }
            if ($error) {
                $canConfirm = false;
            }
            $total = $total->plus($weight);
            $rows[] = ['unit_id' => $unit->id, 'label' => ($unit->block ? $unit->block.' / ' : '').$unit->unit_no,
                'account_id' => $account?->id, 'account_name' => $account?->name, 'consumption' => $reading?->consumption,
                'weight' => (string) $weight, 'amount_cents' => 0, 'error' => $error];
        }
        $cents = (int) round($amount * 100);
        if ($cents <= 0 || $cents > 999999999999) {
            throw ValidationException::withMessages(['source_amount' => 'Toplam tutar 0 ile 9.999.999.999,99 TL arasında olmalı.']);
        }
        $roundUp = (bool) ($input['round_to_integer'] ?? false);
        if ($total->isPositive() && $roundUp) {
            foreach ($rows as &$row) {
                [$whole, $fraction] = BigInteger::of($row['weight'])->multipliedBy($cents)->quotientAndRemainder($total->multipliedBy(100));
                $row['amount_cents'] = ($whole->toInt() + ($fraction->isPositive() ? 1 : 0)) * 100;
            }
            unset($row);
        } elseif ($total->isPositive()) {
            $remainders = [];
            $allocated = 0;
            foreach ($rows as $index => &$row) {
                [$part, $remainder] = BigInteger::of($row['weight'])->multipliedBy($cents)->quotientAndRemainder($total);
                $row['amount_cents'] = $part->toInt();
                $allocated += $row['amount_cents'];
                if ($row['weight'] !== '0') {
                    $remainders[] = ['index' => $index, 'remainder' => $remainder];
                }
            }
            unset($row);
            usort($remainders, fn ($a, $b) => $b['remainder']->compareTo($a['remainder']) ?: $a['index'] <=> $b['index']);
            for ($i = 0; $i < $cents - $allocated; $i++) {
                $rows[$remainders[$i]['index']]['amount_cents']++;
            }
        } else {
            $canConfirm = false;
        }

        return ['period_id' => $period->id, 'type' => $period->consumption_type,
            'start_date' => $period->start_date->format('d.m.Y'), 'end_date' => $period->end_date->format('d.m.Y'),
            'unit' => $period->measurement_unit, 'total_weight' => (string) $total,
            'total_consumption' => (string) $total->quotient(10000).'.'.str_pad((string) $total->remainder(10000), 4, '0', STR_PAD_LEFT),
            'amount_cents' => $cents, 'billed_cents' => array_sum(array_column($rows, 'amount_cents')), 'round_up' => $roundUp, 'can_confirm' => $canConfirm && count($rows) > 0, 'rows' => $rows];
    }
}
