<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\ConsumptionPeriod;
use App\Models\ConsumptionSetting;
use App\Models\Unit;
use App\Models\UnitConsumption;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MeterReadingService
{
    public static function scaled(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return (int) $whole * 10000 + (int) str_pad($fraction, 4, '0');
    }

    public static function decimal(int $value): string
    {
        return intdiv($value, 10000).'.'.str_pad((string) ($value % 10000), 4, '0', STR_PAD_LEFT);
    }

    public static function previous($records, ConsumptionSetting|ConsumptionPeriod $setting): ?string
    {
        $latest = $records->first();
        if (! $latest) {
            return null;
        }

        return $latest->method === 'index' && $latest->measurement_unit === $setting->measurement_unit
            ? $latest->value : null;
    }

    public function saveWithPeriod(int $apartmentId, string $type, ?int $periodId, array $dates, array $data): array
    {
        return DB::transaction(function () use ($apartmentId, $type, $periodId, $dates, $data) {
            Apartment::whereKey($apartmentId)->lockForUpdate()->firstOrFail();
            if ($periodId) {
                throw ValidationException::withMessages(['period_id' => 'Kaydedilmiş dönemlere bu ekrandan okuma eklenemez.']);
            }
            if (! $periodId) {
                $setting = ConsumptionSetting::forType($apartmentId, $type);
                if ($setting->method !== $data['method'] || $setting->measurement_unit !== $data['measurement_unit']) {
                    throw ValidationException::withMessages(['settings' => 'Ölçüm ayarları değişti. Sayfayı yenileyin.']);
                }
                $periods = ConsumptionPeriod::where('apartment_id', $apartmentId)->where('consumption_type', $type);
                $latestEnd = (clone $periods)->max('end_date');
                if ($latestEnd && $dates['start_date'] < $latestEnd) {
                    throw ValidationException::withMessages(['start_date' => 'Başlangıç tarihi son okuma döneminin bitişinden önce olamaz.']);
                }
                $period = ConsumptionPeriod::create(['apartment_id' => $apartmentId, 'consumption_type' => $type, ...$dates,
                    'method' => $setting->method, 'measurement_unit' => $setting->measurement_unit]);
                $periodId = $period->id;
            }
            $count = $this->save($apartmentId, $type, $periodId, $data['values'], $data['initials'] ?? [], $data);

            return [$periodId, $count];
        });
    }

    public function save(int $apartmentId, string $type, int $periodId, array $values, array $initials, array $expected): int
    {
        return DB::transaction(function () use ($apartmentId, $type, $periodId, $values, $initials, $expected) {
            // Serializes settings and readings, including the first ever submission.
            Apartment::whereKey($apartmentId)->lockForUpdate()->firstOrFail();
            $setting = ConsumptionPeriod::where('apartment_id', $apartmentId)->where('consumption_type', $type)->findOrFail($periodId);
            if ($setting->method !== $expected['method'] || $setting->measurement_unit !== $expected['measurement_unit']) {
                throw ValidationException::withMessages(['settings' => 'Ölçüm ayarları değişti. Sayfayı yenileyin.']);
            }
            $count = 0;
            foreach ($values as $id => $value) {
                if ($value === null || $value === '') {
                    continue;
                }
                if (! ctype_digit((string) $id) || (int) $id < 1 || ! Unit::where('apartment_id', $apartmentId)->whereKey($id)->exists()) {
                    throw ValidationException::withMessages(["values.$id" => 'Daire bu apartmana ait değil.']);
                }
                $query = UnitConsumption::where('unit_consumptions.apartment_id', $apartmentId)->where('unit_id', $id)->where('unit_consumptions.consumption_type', $type);
                if ((clone $query)->where('consumption_period_id', $periodId)->exists()) {
                    throw ValidationException::withMessages(["values.$id" => 'Bu daire için bu dönemde tüketim zaten kayıtlı.']);
                }
                $previous = null;
                $consumption = self::scaled((string) $value);
                $next = null;
                if ($setting->method === 'index') {
                    $prior = (clone $query)->join('consumption_periods', 'consumption_periods.id', '=', 'unit_consumptions.consumption_period_id')->select('unit_consumptions.*')->where('consumption_periods.end_date', '<=', $setting->start_date->format('Y-m-d'))->orderByDesc('consumption_periods.end_date')->get();
                    $previous = self::previous($prior, $setting) ?? ($initials[$id] ?? null);
                    if ($previous === null || $previous === '') {
                        throw ValidationException::withMessages(["initials.$id" => 'İlk endeks ölçümü için önceki okumayı girin.']);
                    }
                    $following = (clone $query)->join('consumption_periods', 'consumption_periods.id', '=', 'unit_consumptions.consumption_period_id')->select('unit_consumptions.*')->where('consumption_periods.start_date', '>=', $setting->end_date->format('Y-m-d'))->orderBy('consumption_periods.start_date')->get();
                    if ($following->isNotEmpty()) {
                        $next = $following->first();
                        if ($next->method !== 'index' || $next->measurement_unit !== $setting->measurement_unit) {
                            throw ValidationException::withMessages(["values.$id" => 'Sonraki dönemin ölçüm yapısı farklı. Bu döneme endeks eklenemiyor.']);
                        }
                        // Keep an explicitly entered baseline for the first chronological reading.
                        if ($prior->isEmpty() && self::scaled((string) $previous) !== self::scaled($next->previous_value)) {
                            throw ValidationException::withMessages(["initials.$id" => 'Önceki okuma mevcut ilk dönemin başlangıç değeriyle aynı olmalı.']);
                        }
                    }
                    if ($consumption < self::scaled((string) $previous) || ($next && $consumption > self::scaled($next->value))) {
                        throw ValidationException::withMessages(["values.$id" => 'Yeni okuma önceki okumadan küçük veya sonraki okumadan büyük olamaz.']);
                    }
                    $consumption -= self::scaled((string) $previous);
                }
                UnitConsumption::create([
                    'apartment_id' => $apartmentId, 'unit_id' => $id, 'consumption_type' => $type, 'consumption_period_id' => $periodId,
                    'method' => $setting->method, 'measurement_unit' => $setting->measurement_unit,
                    'value' => $value, 'previous_value' => $previous, 'consumption' => self::decimal($consumption),
                ]);
                if ($next) {
                    $next->update(['previous_value' => $value, 'consumption' => self::decimal(self::scaled($next->value) - self::scaled((string) $value))]);
                }
                $count++;
            }
            if (! $count) {
                throw ValidationException::withMessages(['values' => 'En az bir ölçüm girin. Sıfır tüketim için 0 yazın.']);
            }

            return $count;
        });
    }
}
