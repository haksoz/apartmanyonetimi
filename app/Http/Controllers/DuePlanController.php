<?php

namespace App\Http\Controllers;

use App\Models\AccountTransaction;
use App\Models\Due;
use App\Models\DueBatch;
use App\Models\DuePlan;
use App\Models\TenantAssignment;
use App\Models\Unit;
use App\Support\AidatPeriodReconciliation;
use App\Support\CurrentApartment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DuePlanController extends Controller
{
    public function __construct(private readonly AidatPeriodReconciliation $aidatReconciliation)
    {
    }

    public function index(CurrentApartment $currentApartment, Request $request)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        $plans = DuePlan::query()
            ->where('apartment_id', $apartment->id)
            ->orderByDesc('plan_year')
            ->orderByDesc('id')
            ->get();

        $selectedYear = (int) $request->input('year', $plans->first()?->plan_year ?? now()->year);

        $plan = DuePlan::query()
            ->with(['batches' => fn ($q) => $q->withCount('dues')->orderBy('period')])
            ->where('apartment_id', $apartment->id)
            ->where('plan_year', $selectedYear)
            ->firstOrNew([
                'apartment_id' => $apartment->id,
                'plan_year' => $selectedYear,
            ]);

        if (! $plan->exists) {
            $plan->apartment_id = $apartment->id;
            $plan->plan_year = $selectedYear;
            $plan->generation_mode = DuePlan::GENERATION_ON_SCHEDULE;
            $plan->is_active = true;
        }

        $units = Unit::query()
            ->where('apartment_id', $apartment->id)
            ->orderBy('unit_no')
            ->get(['id', 'unit_no', 'block', 'square_meters', 'share_coefficient']);

        $periods = $this->buildPeriodsList($plan, $units);

        $currentPeriod = now()->format('Y-m');
        $shouldPromptGenerate = session('prompt_generate_period') === $currentPeriod
            && $plan->exists
            && $plan->is_active
            && (int) session('prompt_generate_plan_id') === (int) $plan->id;

        return view('due-plans.index', compact(
            'plan',
            'plans',
            'apartment',
            'units',
            'periods',
            'currentPeriod',
            'shouldPromptGenerate',
            'selectedYear',
        ));
    }

    private function buildPeriodsList(DuePlan $plan, $units): array
    {
        if (! $plan->exists || ! $plan->start_date || ! $plan->end_date) {
            return [];
        }

        $periods = [];
        $cursor = $plan->start_date->copy()->startOfMonth();
        $end = $plan->end_date->copy()->startOfMonth();
        $planStopped = ! $plan->is_active;

        while ($cursor <= $end) {
            $period = $cursor->format('Y-m');
            $periodDate = $cursor->copy();
            $batch = $plan->batches->firstWhere('period', $period);
            $reconciliation = $this->aidatReconciliation->reconcile($plan, $period);
            $activeCount = count($reconciliation['completed_account_ids']);
            $expectedCount = $reconciliation['target_accounts']->count();

            if ($activeCount === 0) {
                $status = $planStopped ? 'stopped' : 'not_generated';
            } elseif ($activeCount < $expectedCount) {
                $status = 'incomplete';
            } else {
                $status = 'complete';
            }

            $periods[] = [
                'period' => $period,
                'label' => $periodDate->locale('tr')->isoFormat('MMMM YYYY'),
                'status' => $status,
                'active_count' => $activeCount,
                'expected_count' => $expectedCount,
                'batch_id' => $batch?->id,
                'is_past' => $period < now()->format('Y-m'),
            ];

            $cursor->addMonth();
        }

        return $periods;
    }

    public function create(CurrentApartment $currentApartment)
    {
        return redirect()->route('due-plans.index');
    }

    public function regeneratePeriod(Request $request, CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        if ($duePlan->apartment_id !== $apartment->id) {
            abort(404);
        }

        if (! $duePlan->is_active) {
            return redirect()->route('due-plans.index', ['year' => $duePlan->plan_year])
                ->with('error', 'Pasif plan üzerinden eksik aidat oluşturulamaz.');
        }

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);

        $period = $validated['period'];
        $periodDate = Carbon::parse($period.'-01');

        if (! $this->periodWithinPlan($duePlan, $periodDate)) {
            return redirect()->route('due-plans.index', ['year' => $duePlan->plan_year])
                ->with('error', 'Seçilen dönem plan yılı içinde değil.');
        }

        $createdCount = $this->createDuesForPeriod($duePlan, $period);

        if ($createdCount === 0) {
            return redirect()->route('due-plans.index', ['year' => $duePlan->plan_year])
                ->with('status', 'Seçilen dönemde eksik aidat bulunmuyor.');
        }

        return redirect()->route('due-plans.index', ['year' => $duePlan->plan_year])
            ->with('status', "{$period} dönemi için {$createdCount} eksik aidat oluşturuldu.");
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        $validated = $request->validate([
            'plan_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'monthly_amount' => ['required', 'numeric', 'min:0.01'],
            'distribution_type' => ['required', Rule::in(['equal', 'square_meters', 'share_coefficient'])],
            'round_to_integer' => ['nullable', 'boolean'],
            'target_audience' => ['required', Rule::in(['tenant_priority', 'owner_only'])],
            'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'generate_day' => ['required', 'integer', 'min:1', 'max:28'],
            'generation_mode' => ['required', Rule::in([DuePlan::GENERATION_ON_SCHEDULE, DuePlan::GENERATION_CREATE_ALL_NOW])],
            'include_past_periods' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $planYear = (int) $validated['plan_year'];
        $generationMode = $validated['generation_mode'];

        $payload = [
            'apartment_id' => $apartment->id,
            'name' => 'Aidat Kararı',
            'plan_year' => $planYear,
            'start_date' => sprintf('%04d-01-01', $planYear),
            'end_date' => sprintf('%04d-12-31', $planYear),
            'amount_type' => 'monthly',
            'monthly_amount' => $validated['monthly_amount'],
            'distribution_type' => $validated['distribution_type'],
            'round_to_integer' => $request->boolean('round_to_integer'),
            'target_audience' => $validated['target_audience'],
            'due_day' => $validated['due_day'],
            'generate_day' => $validated['generate_day'],
            'generation_mode' => $generationMode,
            'description' => $validated['description'] ?? null,
            'due_type' => 'aidat',
            'category_id' => $this->aidatReconciliation->categoryFor($apartment)->id,
            'auto_generate' => $generationMode === DuePlan::GENERATION_ON_SCHEDULE,
            'is_active' => $request->boolean('is_active', true),
        ];

        $plan = DuePlan::query()
            ->where('apartment_id', $apartment->id)
            ->where('plan_year', $planYear)
            ->first();

        if ($plan) {
            $plan->update($payload);
        } else {
            $plan = DuePlan::create($payload);
        }

        $plan->refresh();

        if ($plan->is_active && $generationMode === DuePlan::GENERATION_CREATE_ALL_NOW) {
            $includePast = $request->boolean('include_past_periods');

            $created = $this->generateAllEligiblePeriods($plan, $includePast);
            $message = $created > 0
                ? "Aidat planı kaydedildi. {$created} daireye aidat oluşturuldu."
                : 'Aidat planı kaydedildi. Oluşturulacak eksik aidat bulunamadı.';

            return redirect()->route('due-plans.index', ['year' => $plan->plan_year])
                ->with('status', $message);
        }

        $currentPeriod = now()->format('Y-m');
        $today = now()->toDateString();

        if (
            $plan->is_active
            && $plan->createsOnSchedule()
            && $today >= $plan->start_date->toDateString()
            && $today <= $plan->end_date->toDateString()
            && ! $plan->isGeneratedForPeriod($currentPeriod)
        ) {
            return redirect()->route('due-plans.index', ['year' => $plan->plan_year])
                ->with('prompt_generate_period', $currentPeriod)
                ->with('prompt_generate_plan_id', $plan->id)
                ->with('status', 'Aidat planı kaydedildi.');
        }

        return redirect()->route('due-plans.index', ['year' => $plan->plan_year])
            ->with('status', 'Aidat planı kaydedildi.');
    }

    public function confirmPastPeriods(Request $request, CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        if ($duePlan->apartment_id !== $apartment->id) {
            abort(404);
        }

        if (! $duePlan->is_active) {
            return redirect()->route('due-plans.index', ['year' => $duePlan->plan_year])
                ->with('error', 'Pasif plan üzerinden aidat oluşturulamaz.');
        }

        $includePast = $request->boolean('include_past_periods');
        $created = $this->generateAllEligiblePeriods($duePlan, $includePast);

        return redirect()->route('due-plans.index', ['year' => $duePlan->plan_year])
            ->with('status', $created > 0
                ? "{$created} daireye aidat oluşturuldu."
                : 'Oluşturulacak eksik aidat bulunamadı.');
    }

    public function edit(CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        return redirect()->route('due-plans.index', ['year' => $duePlan->plan_year]);
    }

    public function update(Request $request, CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        return redirect()->route('due-plans.index', ['year' => $duePlan->plan_year]);
    }

    public function destroy(CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        if ($duePlan->apartment_id !== $apartment->id) {
            abort(404);
        }

        $year = $duePlan->plan_year;
        $duePlan->delete();

        return redirect()->route('due-plans.index', ['year' => $year])->with('status', 'Aidat planı silindi.');
    }

    public function deactivate(CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        if ($duePlan->apartment_id !== $apartment->id) {
            abort(404);
        }

        $duePlan->update(['is_active' => false]);

        return redirect()->route('due-plans.index', ['year' => $duePlan->plan_year])
            ->with('status', 'Aidat planı durduruldu. Mevcut borçlar korundu.');
    }

    public function activate(CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        if ($duePlan->apartment_id !== $apartment->id) {
            abort(404);
        }

        $duePlan->update(['is_active' => true]);

        return redirect()->route('due-plans.index', ['year' => $duePlan->plan_year])
            ->with('status', 'Aidat planı yeniden aktifleştirildi.');
    }

    public function generateMonth(Request $request, CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        if ($duePlan->apartment_id !== $apartment->id) {
            abort(404);
        }

        if (! $duePlan->is_active) {
            return redirect()->route('due-plans.index', ['year' => $duePlan->plan_year])
                ->with('error', 'Pasif plan üzerinden aidat oluşturulamaz.');
        }

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $period = $validated['period'];
        $periodDate = Carbon::parse($period.'-01');

        if (! $this->periodWithinPlan($duePlan, $periodDate)) {
            return redirect()->route('due-plans.index', ['year' => $duePlan->plan_year])
                ->with('error', 'Seçilen dönem plan yılı içinde değil.');
        }

        $count = $this->createDuesForPeriod($duePlan, $period, $validated['description'] ?? null);

        if ($count === 0) {
            return redirect()->route('due-plans.index', ['year' => $duePlan->plan_year])
                ->with('error', 'Seçilen dönem için oluşturulacak eksik aidat bulunmuyor.');
        }

        return redirect()->route('dues.index')
            ->with('status', "{$period} dönemi için {$count} daireye aidat oluşturuldu.");
    }

    public function createDuesForPeriod(DuePlan $duePlan, string $period, ?string $customDescription = null, bool $allowPartial = true): int
    {
        if (! $duePlan->is_active) {
            return 0;
        }

        $periodDate = Carbon::parse($period.'-01');

        if (! $this->periodWithinPlan($duePlan, $periodDate)) {
            return 0;
        }

        $reconciliation = $this->aidatReconciliation->reconcile($duePlan, $period);
        $unitAccounts = $reconciliation['missing_accounts']->all();

        if (empty($unitAccounts) || (! $allowPartial && ! empty($reconciliation['completed_account_ids']))) {
            return 0;
        }

        $category = $reconciliation['category'];
        if ($duePlan->category_id !== $category->id) {
            $duePlan->update(['category_id' => $category->id]);
        }

        $totalWeight = array_sum(array_column($unitAccounts, 'weight'));
        $dueDate = $periodDate->copy()->setDay(min($duePlan->due_day, $periodDate->daysInMonth));
        $monthlyAmount = $duePlan->monthly_amount_resolved;
        $roundToInteger = (bool) $duePlan->round_to_integer;
        if ($roundToInteger) {
            $monthlyAmount = (float) ceil($monthlyAmount);
        }
        $monthName = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'][(int) $periodDate->format('n')];
        $batchDescription = $customDescription ?: "{$periodDate->year} {$monthName} - Aidat";
        $count = count($unitAccounts);
        $allocated = 0;
        $snapshot = [];
        $billedTotal = 0.0;

        foreach ($unitAccounts as $index => $item) {
            $isLast = $index === $count - 1;
            if ($duePlan->distribution_type === DuePlan::DISTRIBUTION_EQUAL) {
                $amount = $roundToInteger
                    ? (float) ceil($monthlyAmount / $count)
                    : ($isLast ? round($monthlyAmount - $allocated, 2) : round($monthlyAmount / $count, 2));
            } else {
                if ($totalWeight <= 0) {
                    $amount = $roundToInteger
                        ? (float) ceil($monthlyAmount / $count)
                        : ($isLast ? round($monthlyAmount - $allocated, 2) : round($monthlyAmount / $count, 2));
                } else {
                    $amount = $roundToInteger
                        ? (float) ceil($monthlyAmount * $item['weight'] / $totalWeight)
                        : ($isLast ? round($monthlyAmount - $allocated, 2) : round($monthlyAmount * $item['weight'] / $totalWeight, 2));
                }
            }
            if (! $roundToInteger) {
                $allocated += $amount;
            }
            $billedTotal += $amount;
            $snapshot[$item['unit']->id] = $amount;
        }

        $sourceAmount = $roundToInteger ? $billedTotal : $monthlyAmount;

        DB::transaction(function () use ($duePlan, $period, $periodDate, $dueDate, $sourceAmount, $batchDescription, $snapshot, $unitAccounts, $category) {
            $batch = DueBatch::query()->firstOrCreate(
                ['due_plan_id' => $duePlan->id, 'period' => $period],
                [
                    'apartment_id' => $duePlan->apartment_id,
                    'due_type' => $duePlan->due_type,
                    'category_id' => $category->id,
                    'source_type' => DueBatch::SOURCE_MANUAL,
                    'distribution_type' => $duePlan->distribution_type,
                    'target_audience' => $duePlan->target_audience,
                    'source_amount' => $sourceAmount,
                    'description' => $batchDescription,
                    'distribution_snapshot' => $snapshot,
                    'created_by' => auth()->id(),
                ],
            );

            $batch->update([
                'category_id' => $category->id,
                'source_amount' => $sourceAmount,
                'description' => $batchDescription,
                'distribution_snapshot' => $snapshot,
                'created_by' => auth()->id(),
            ]);

            foreach ($unitAccounts as $item) {
                $amount = $snapshot[$item['unit']->id];
                $due = Due::create([
                    'apartment_id' => $duePlan->apartment_id,
                    'due_batch_id' => $batch->id,
                    'unit_id' => $item['unit']->id,
                    'account_id' => $item['account']->id,
                    'due_type' => $duePlan->due_type,
                    'category_id' => $category->id,
                    'period' => $period,
                    'amount' => $amount,
                    'remaining_amount' => $amount,
                    'due_date' => $dueDate,
                    'status' => 'unpaid',
                    'description' => $batchDescription,
                    'created_at_manual' => $periodDate->copy()->startOfMonth()->toDateString(),
                ]);

                AccountTransaction::create([
                    'apartment_id' => $duePlan->apartment_id,
                    'account_id' => $item['account']->id,
                    'transactionable_type' => Due::class,
                    'transactionable_id' => $due->id,
                    'type' => 'debit',
                    'description' => $batchDescription,
                    'amount' => $amount,
                    'transaction_date' => $periodDate->copy()->startOfMonth()->toDateString(),
                ]);
            }
        });

        return $count;
    }

    private function generateAllEligiblePeriods(DuePlan $plan, bool $includePast): int
    {
        if (! $plan->is_active) {
            return 0;
        }

        $currentPeriod = now()->format('Y-m');
        $total = 0;
        $cursor = $plan->start_date->copy()->startOfMonth();
        $end = $plan->end_date->copy()->startOfMonth();

        while ($cursor <= $end) {
            $period = $cursor->format('Y-m');
            $isPast = $period < $currentPeriod;

            if ($isPast && ! $includePast) {
                $cursor->addMonth();
                continue;
            }

            $total += $this->createDuesForPeriod($plan, $period);
            $cursor->addMonth();
        }

        return $total;
    }

    private function periodWithinPlan(DuePlan $duePlan, Carbon $periodDate): bool
    {
        return ! (
            $periodDate->copy()->endOfMonth()->toDateString() < $duePlan->start_date->toDateString()
            || $periodDate->copy()->startOfMonth()->toDateString() > $duePlan->end_date->toDateString()
        );
    }

    private function getAccountForPeriod(Unit $unit, Carbon $periodDate, string $targetAudience): ?\App\Models\Account
    {
        if ($targetAudience === 'owner_only') {
            return $unit->ownerAccount;
        }

        $tenantAssignment = TenantAssignment::query()
            ->where('unit_id', $unit->id)
            ->where('move_in_date', '<=', $periodDate->endOfMonth())
            ->where(fn ($q) => $q->whereNull('move_out_date')->orWhere('move_out_date', '>=', $periodDate->startOfMonth()))
            ->with('account')
            ->first();

        if ($tenantAssignment && $tenantAssignment->account) {
            return $tenantAssignment->account;
        }

        return $unit->ownerAccount;
    }

    private function getUnitAccountsForPeriod(DuePlan $duePlan, Carbon $periodDate, ?\Illuminate\Database\Eloquent\Collection $units = null): array
    {
        $units ??= Unit::query()
            ->with(['ownerAccount', 'accounts'])
            ->where('apartment_id', $duePlan->apartment_id)
            ->orderBy('unit_no')
            ->get();

        $unitAccounts = [];
        $totalWeight = 0;

        foreach ($units as $unit) {
            $account = $this->getAccountForPeriod($unit, $periodDate, $duePlan->target_audience);
            if (! $account) {
                continue;
            }

            $weight = match ($duePlan->distribution_type) {
                DuePlan::DISTRIBUTION_SQUARE_METERS => (float) ($unit->square_meters ?? 0),
                DuePlan::DISTRIBUTION_SHARE_COEFFICIENT => (float) ($unit->share_coefficient ?? 0),
                default => 1,
            };

            if ($weight <= 0 && $duePlan->distribution_type !== DuePlan::DISTRIBUTION_EQUAL) {
                continue;
            }

            $unitAccounts[] = ['unit' => $unit, 'account' => $account, 'weight' => $weight];
            $totalWeight += $weight;
        }

        return [$unitAccounts, $totalWeight];
    }

    private function resolveApartment(CurrentApartment $currentApartment)
    {
        $apartment = $currentApartment->getFor(auth()->user());

        if (! $apartment && $currentApartment->hasAvailableFor(auth()->user())) {
            return redirect()->route('current-apartment.select');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        return $apartment;
    }
}
