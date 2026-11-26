<?php

namespace App\Http\Controllers;

use App\Models\Apartment;
use App\Models\ConsumptionPeriod;
use App\Models\ConsumptionSetting;
use App\Models\Unit;
use App\Models\UnitConsumption;
use App\Services\MeterReadingService;
use App\Support\CurrentApartment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MeterController extends Controller
{
    private function apartment(CurrentApartment $current)
    {
        return $current->getFor(auth()->user()) ?? abort(403, 'Önce apartman seçin.');
    }

    private function selection(Request $request): array
    {
        $request->merge(['consumption_type' => $request->input('consumption_type', 'cold_water')]);

        return $request->validate(['consumption_type' => ['required', Rule::in(array_keys(ConsumptionSetting::TYPES))], 'period_id' => ['nullable', 'integer']]);
    }

    public function readings(Request $request, CurrentApartment $current)
    {
        $apartment = $this->apartment($current);
        $filters = $this->selection($request);
        if (! empty($filters['period_id'])) {
            $period = ConsumptionPeriod::where('apartment_id', $apartment->id)->where('consumption_type', $filters['consumption_type'])->findOrFail($filters['period_id']);

            return redirect()->route('meters.history.detail', $period->id);
        }
        $setting = ConsumptionSetting::forType($apartment->id, $filters['consumption_type']);
        $units = Unit::where('apartment_id', $apartment->id)->orderBy('block')->orderBy('unit_no')->get();
        $records = UnitConsumption::with('readingPeriod')->where('apartment_id', $apartment->id)->where('consumption_type', $filters['consumption_type'])
            ->get()->sortByDesc(fn ($r) => $r->readingPeriod->end_date->format('Y-m-d'))->groupBy('unit_id');
        $minimumStart = ConsumptionPeriod::where('apartment_id', $apartment->id)->where('consumption_type', $filters['consumption_type'])->max('end_date');
        $suggestedStart = $minimumStart ?? now()->startOfMonth()->format('Y-m-d');
        $startDate = old('start_date', $suggestedStart);

        return view('meters.readings', compact('apartment', 'units', 'records', 'filters', 'setting', 'suggestedStart', 'startDate', 'minimumStart'));
    }

    public function updateSettings(Request $request, CurrentApartment $current)
    {
        $apartment = $this->apartment($current);
        $filters = $this->selection($request);
        $data = $request->validate(['method' => ['required', Rule::in(array_keys(ConsumptionSetting::METHODS))], 'measurement_unit' => ['required', 'string', 'max:30']]);
        DB::transaction(function () use ($apartment, $filters, $data) {
            Apartment::whereKey($apartment->id)->lockForUpdate()->firstOrFail();
            ConsumptionSetting::updateOrCreate(['apartment_id' => $apartment->id, 'consumption_type' => $filters['consumption_type']], $data);
        });

        return redirect()->route('meters.index', $filters)->with('status', 'Bu tüketim türünün apartman genelindeki ayarları kaydedildi. Geçmiş kayıtlar korunur.');
    }

    public function storeReadings(Request $request, CurrentApartment $current, MeterReadingService $service)
    {
        $apartment = $this->apartment($current);
        $filters = $this->selection($request);
        $number = ['nullable', 'numeric', 'min:0', 'max:999999999999.9999', 'regex:/^\d{1,12}(\.\d{1,4})?$/'];
        $data = $request->validate([
            'values' => ['required', 'array', 'max:5000'], 'values.*' => $number,
            'initials' => ['sometimes', 'array', 'max:5000'], 'initials.*' => $number,
            'method' => ['required', Rule::in(array_keys(ConsumptionSetting::METHODS))], 'measurement_unit' => ['required', 'string', 'max:30'],
        ]);
        $dates = $request->validate(['start_date' => ['required_without:period_id', 'nullable', 'date_format:Y-m-d'], 'end_date' => ['required_without:period_id', 'nullable', 'date_format:Y-m-d', 'after:start_date']]);
        [$periodId, $count] = $service->saveWithPeriod($apartment->id, $filters['consumption_type'], $filters['period_id'] ?? null, $dates, $data);

        return redirect()->route('meters.history.detail', $periodId)->with('status', "$count dairenin tüketimi kaydedildi.");
    }

    public function history(Request $request, CurrentApartment $current)
    {
        $apartment = $this->apartment($current);
        $filters = $request->validate([
            'consumption_type' => ['nullable', Rule::in(array_keys(ConsumptionSetting::TYPES))],
        ]);
        $query = UnitConsumption::where('unit_consumptions.apartment_id', $apartment->id);
        if (! empty($filters['consumption_type'])) {
            $query->where('unit_consumptions.consumption_type', $filters['consumption_type']);
        }
        $periodSummaries = (clone $query)->join('consumption_periods', 'consumption_periods.id', '=', 'unit_consumptions.consumption_period_id')
            ->selectRaw('consumption_periods.id, consumption_periods.start_date, consumption_periods.end_date, unit_consumptions.consumption_type, unit_consumptions.method, unit_consumptions.measurement_unit, COUNT(*) as reading_count, SUM(unit_consumptions.consumption) as total')
            ->groupBy('consumption_periods.id', 'consumption_periods.start_date', 'consumption_periods.end_date', 'unit_consumptions.consumption_type', 'unit_consumptions.method', 'unit_consumptions.measurement_unit')
            ->orderByDesc('consumption_periods.end_date')->orderByDesc('consumption_periods.id')->paginate(30)->appends($filters);

        $distributionCounts = \App\Models\DueBatch::where('apartment_id', $apartment->id)
            ->whereIn('consumption_period_id', $periodSummaries->pluck('id'))->whereNull('consumption_cancelled_at')
            ->with('dues:id,due_batch_id,unit_id')->orderByDesc('id')->get()->groupBy('consumption_period_id')
            ->map(function ($batches) {
                $batch = $batches->first();
                $expectedIds = collect($batch->distribution_snapshot['rows'] ?? [])->filter(fn ($row) => ($row['amount_cents'] ?? 0) > 0)->pluck('unit_id')->unique();
                return ['expected' => $expectedIds->count(), 'existing' => $batch->dues->whereIn('unit_id', $expectedIds)->pluck('unit_id')->unique()->count()];
            });
        return view('meters.history', compact('filters', 'periodSummaries', 'distributionCounts'));
    }

    public function historyDetail(Request $request, string $period, CurrentApartment $current)
    {
        $apartment = $this->apartment($current);
        $period = ConsumptionPeriod::where('apartment_id', $apartment->id)->findOrFail($period);
        $query = UnitConsumption::where('apartment_id', $apartment->id)->where('consumption_period_id', $period->id);
        $total = (clone $query)->sum('consumption');
        $count = (clone $query)->count();
        $readings = $query->with('unit')->orderBy('unit_id')->paginate(50);

        $batches = $period->batches()->orderByDesc('id')->get();
        $tracking = $batches->mapWithKeys(fn ($batch) => [$batch->id => app(\App\Services\ConsumptionBatchTracking::class)->summary($batch)]);
        $activeBatch = $batches->first(fn ($batch) => ! $batch->consumption_cancelled_at);
        $billingRows = $activeBatch ? collect($tracking[$activeBatch->id]['rows'])->keyBy('unit_id') : collect();
        return view('meters.history-detail', compact('period', 'readings', 'total', 'count', 'batches', 'tracking', 'activeBatch', 'billingRows'));
    }
}
