<?php

namespace App\Http\Controllers;

use App\Models\AccountTransaction;
use App\Models\Due;
use App\Models\DueBatch;
use App\Models\DuePlan;
use App\Models\TenantAssignment;
use App\Models\Unit;
use App\Support\AidatPeriodReconciliation;
use App\Support\AidatPlanSummary;
use Illuminate\Support\Str;
use App\Support\CurrentApartment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

        $request->validate(['plan_id' => ['nullable', 'integer', 'min:1']]);
        $plan = $request->boolean('new') ? new DuePlan() : DuePlan::query()
            ->with(['batches' => fn ($q) => $q->withCount('dues')->orderBy('period')])
            ->where('apartment_id', $apartment->id)
            ->where('plan_year', $selectedYear)
            ->when($request->filled('plan_id'), fn ($query) => $query->whereKey($request->integer('plan_id')))
            ->orderByDesc('is_active')->orderByDesc('id')
            ->firstOrNew([
                'apartment_id' => $apartment->id,
                'plan_year' => $selectedYear,
            ]);

        if ($request->filled('plan_id') && ! $plan->exists) abort(404);

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

            if ($plan->skipsPeriod($period) && $activeCount < $expectedCount) {
                $status = 'skipped';
            } elseif ($activeCount === 0) {
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
                'scheduled_for' => $plan->is_active && $plan->createsOnSchedule()
                    && $status === 'not_generated' && $expectedCount > 0 && $period >= now()->format('Y-m')
                    ? $periodDate->copy()->setDay($plan->generate_day) : null,
                'is_past' => $period < now()->format('Y-m'),
            ];

            $cursor->addMonth();
        }

        return $periods;
    }

    public function create(CurrentApartment $currentApartment)
    {
        return redirect()->route('due-plans.index', ['year' => now()->year, 'new' => 1]);
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
            return redirect()->route('due-plans.index', $duePlan->pageParameters())
                ->with('error', 'Pasif plan üzerinden eksik aidat oluşturulamaz.');
        }

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);

        $period = $validated['period'];
        $periodDate = Carbon::parse($period.'-01');

        if (! $this->periodWithinPlan($duePlan, $periodDate)) {
            return redirect()->route('due-plans.index', $duePlan->pageParameters())
                ->with('error', 'Seçilen dönem plan yılı içinde değil.');
        }

        $createdCount = $this->createDuesForPeriod($duePlan, $period);

        if ($createdCount === 0) {
            return redirect()->route('due-plans.index', $duePlan->pageParameters())
                ->with('status', 'Seçilen dönemde eksik aidat bulunmuyor.');
        }

        return redirect()->route('due-plans.index', $duePlan->pageParameters())
            ->with('status', "{$period} dönemi için {$createdCount} eksik aidat oluşturuldu.");
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        $confirmation = null;
        $choiceChanged = false;
        $refreshSummary = $request->boolean('update_summary');
        if ($request->filled('confirmation_token')) {
            $confirmation = $request->session()->get('aidat_plan_confirmation');
            if (! $confirmation || ! hash_equals($confirmation['token'], (string) $request->input('confirmation_token'))
                || $confirmation['apartment_id'] !== $apartment->id || $confirmation['user_id'] !== auth()->id()) {
                return redirect()->route('due-plans.index', ['year' => now()->year])
                    ->with('error', 'Onay süresi doldu. Planı yeniden kontrol edip kaydedin.');
            }
            $returnToForm = $request->boolean('return_to_form');
            $choice = $request->validate([
                'current_month_action' => ['sometimes', 'nullable', Rule::in(['create', 'skip'])],
                'include_past_periods' => ['sometimes', 'boolean'],
            ]);
            $choiceChanged = (array_key_exists('current_month_action', $choice)
                && ($confirmation['input']['current_month_action'] ?? null) !== $choice['current_month_action'])
                || (array_key_exists('include_past_periods', $choice)
                    && (bool) ($confirmation['input']['include_past_periods'] ?? false) !== (bool) $choice['include_past_periods']);
            $request->replace($confirmation['input']);
            $request->merge($choice);
            if ($returnToForm) {
                $request->session()->forget('aidat_plan_confirmation');
                return redirect()->route('due-plans.index', array_filter([
                    'year' => $request->input('plan_year'), 'edit' => 1,
                    'plan_id' => $request->input('plan_id'), 'new' => $request->boolean('creating_new') ? 1 : null,
                ]))->withInput();
            }
        }

        $validated = $request->validate([
            'plan_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'plan_id' => ['nullable', 'integer', Rule::exists('due_plans', 'id')->where('apartment_id', $apartment->id)->where('plan_year', $request->input('plan_year'))],
            'creating_new' => ['sometimes', 'boolean'],
            'monthly_amount' => ['required', 'numeric', 'min:0.01'],
            'distribution_type' => ['required', Rule::in(['equal', 'square_meters', 'share_coefficient'])],
            'round_to_integer' => ['nullable', 'boolean'],
            'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'generate_day' => ['required', 'integer', 'min:1', 'max:28'],
            'include_past_periods' => ['nullable', 'boolean'],
            'current_month_action' => ['nullable', Rule::in(['create', 'skip'])],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ]);

        $planYear = (int) $validated['plan_year'];
        $generationMode = DuePlan::GENERATION_ON_SCHEDULE;

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
            'target_audience' => 'tenant_priority',
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
            ->when($request->filled('plan_id'), fn ($query) => $query->whereKey($request->integer('plan_id')),
                fn ($query) => $query->where('is_active', true))
            ->first();

        if ($request->boolean('creating_new')) {
            if (DuePlan::where('apartment_id', $apartment->id)->where('plan_year', $planYear)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['plan_year' => 'Bu yıl için aktif bir aidat planı zaten var.']);
            }
            $plan = null;
        }
        if ($request->boolean('is_active', true) && DuePlan::where('apartment_id', $apartment->id)->where('plan_year', $planYear)
            ->where('is_active', true)->when($plan, fn ($query) => $query->whereKeyNot($plan->id))->exists()) {
            throw ValidationException::withMessages(['is_active' => 'Bu yıl için başka bir aktif aidat planı var.']);
        }

        $proposed = $plan ? clone $plan : new DuePlan();
        $proposed->fill($payload);
        $proposed->setRelation('apartment', $apartment);
        $summary = app(AidatPlanSummary::class)->build($proposed, $request->boolean('include_past_periods'), $request->input('current_month_action'));
        $fingerprint = hash('sha256', json_encode($summary));
        if (! $confirmation || $refreshSummary || ! hash_equals($confirmation['fingerprint'], $fingerprint)
            || ($summary['current']['eligible'] && $summary['current']['action'] === null)) {
            return $this->confirmationView($request, $apartment->id, $validated, $summary, $proposed, $confirmation !== null && ! $choiceChanged);
        }

        $savedPlan = null;
        $created = DB::transaction(function () use ($payload, $request, $validated, $fingerprint, &$savedPlan) {
            $apartment = \App\Models\Apartment::query()->lockForUpdate()->findOrFail($payload['apartment_id']);
            $plan = DuePlan::query()->where('apartment_id', $apartment->id)
                ->where('plan_year', $payload['plan_year'])
                ->when($request->filled('plan_id'), fn ($query) => $query->whereKey($request->integer('plan_id')),
                    fn ($query) => $query->where('is_active', true))->lockForUpdate()->first();
            if ($request->boolean('creating_new')) {
                if (DuePlan::where('apartment_id', $apartment->id)->where('plan_year', $payload['plan_year'])->where('is_active', true)->exists()) {
                    throw ValidationException::withMessages(['plan_year' => 'Bu yıl için aktif bir aidat planı zaten var.']);
                }
                $plan = null;
            }
            $latest = $plan ? clone $plan : new DuePlan();
            $latest->fill($payload)->setRelation('apartment', $apartment);
            $summary = app(AidatPlanSummary::class)->build($latest, $request->boolean('include_past_periods'), $request->input('current_month_action'));
            if (! hash_equals($fingerprint, hash('sha256', json_encode($summary)))) {
                return $this->confirmationView($request, $apartment->id, $validated, $summary, $latest, true);
            }
            $payload['category_id'] = $this->aidatReconciliation->categoryFor(
                \App\Models\Apartment::findOrFail($payload['apartment_id'])
            )->id;
            if ($plan) {
                $savedPlan = DuePlan::query()->lockForUpdate()->findOrFail($plan->id);
                $savedPlan->update($payload);
            } else {
                $savedPlan = DuePlan::create($payload);
            }
            $created = $this->generatePastPeriods($savedPlan, $request->boolean('include_past_periods'));
            if ($summary['current']['eligible']) {
                $period = $summary['current']['period'];
                $skipped = array_values(array_diff($savedPlan->skipped_periods ?? [], [$period]));
                if ($summary['current']['action'] === 'skip') $skipped[] = $period;
                $savedPlan->update(['skipped_periods' => $skipped]);
                if ($summary['current']['action'] === 'create') {
                    $created += $this->createDuesForPeriod($savedPlan, $period);
                }
            }
            return $created;
        });
        if (! is_int($created)) return $created;
        $request->session()->forget('aidat_plan_confirmation');

        return redirect()->route('due-plans.index', $savedPlan->pageParameters())
            ->with('status', "Aidat planı kaydedildi. {$created} aidat kaydı oluşturuldu. Mevcut borçlar korundu.");
    }

    private function confirmationView(Request $request, int $apartmentId, array $validated, array $summary, DuePlan $proposed, bool $changed)
    {
        $token = (string) Str::uuid();
        $request->session()->put('aidat_plan_confirmation', [
            'token' => $token, 'fingerprint' => hash('sha256', json_encode($summary)),
            'apartment_id' => $apartmentId, 'user_id' => auth()->id(),
            'input' => $request->only(array_keys($validated)),
        ]);
        return view('due-plans.confirm', [
            'summary' => $summary, 'token' => $token, 'planYear' => $proposed->plan_year,
            'changed' => $changed, 'proposed' => $proposed,
        ]);
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
            return redirect()->route('due-plans.index', $duePlan->pageParameters())
                ->with('error', 'Pasif plan üzerinden aidat oluşturulamaz.');
        }

        $includePast = $request->boolean('include_past_periods');
        $created = $this->generatePastPeriods($duePlan, $includePast);

        return redirect()->route('due-plans.index', $duePlan->pageParameters())
            ->with('status', $created > 0
                ? "{$created} daireye aidat oluşturuldu."
                : 'Oluşturulacak eksik aidat bulunamadı.');
    }

    public function edit(CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        return redirect()->route('due-plans.index', $duePlan->pageParameters());
    }

    public function update(Request $request, CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        return redirect()->route('due-plans.index', $duePlan->pageParameters());
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

        return redirect()->route('due-plans.index', $duePlan->pageParameters())
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

        return redirect()->route('due-plans.index', $duePlan->pageParameters())
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
            return redirect()->route('due-plans.index', $duePlan->pageParameters())
                ->with('error', 'Pasif plan üzerinden aidat oluşturulamaz.');
        }

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $period = $validated['period'];
        $periodDate = Carbon::parse($period.'-01');

        if (! $this->periodWithinPlan($duePlan, $periodDate)) {
            return redirect()->route('due-plans.index', $duePlan->pageParameters())
                ->with('error', 'Seçilen dönem plan yılı içinde değil.');
        }

        $count = $this->createDuesForPeriod($duePlan, $period, $validated['description'] ?? null);

        if ($count === 0) {
            return redirect()->route('due-plans.index', $duePlan->pageParameters())
                ->with('error', 'Seçilen dönem için oluşturulacak eksik aidat bulunmuyor.');
        }

        return redirect()->route('dues.index')
            ->with('status', "{$period} dönemi için {$count} daireye aidat oluşturuldu.");
    }

    public function createDuesForPeriod(DuePlan $duePlan, string $period, ?string $customDescription = null, bool $allowPartial = true): int
    {
        return DB::transaction(function () use ($duePlan, $period, $customDescription, $allowPartial) {
            $lockedPlan = DuePlan::query()->lockForUpdate()->findOrFail($duePlan->id);
            return $this->createLockedPeriod($lockedPlan, $period, $customDescription, $allowPartial);
        });
    }

    private function createLockedPeriod(DuePlan $duePlan, string $period, ?string $customDescription, bool $allowPartial): int
    {
        if ($period > now()->format('Y-m')) {
            return 0;
        }
        if ($duePlan->skipsPeriod($period)) {
            return 0;
        }
        if (! $duePlan->is_active) {
            return 0;
        }

        $periodDate = Carbon::parse($period.'-01');

        if (! $this->periodWithinPlan($duePlan, $periodDate)) {
            return 0;
        }

        $reconciliation = app(AidatPlanSummary::class)->period($duePlan, $period);
        $unitAccounts = $reconciliation['missing_accounts']->all();

        if (empty($unitAccounts) || (! $allowPartial && ! empty($reconciliation['completed_account_ids']))) {
            return 0;
        }

        $category = $reconciliation['category'] ?? $this->aidatReconciliation->categoryFor($duePlan->apartment);
        if ($duePlan->category_id !== $category->id) {
            $duePlan->update(['category_id' => $category->id]);
        }

        $dueDate = $periodDate->copy()->setDay(min($duePlan->due_day, $periodDate->daysInMonth));
        $batchDescription = $customDescription ?: $periodDate->copy()->locale('tr')->isoFormat('YYYY MMMM').' - Aidatı';
        $count = count($unitAccounts);
        $snapshot = $reconciliation['snapshot'];
        $sourceAmount = $reconciliation['source_amount'];

        DB::transaction(function () use ($duePlan, $period, $periodDate, $dueDate, $sourceAmount, $batchDescription, $snapshot, $unitAccounts, $category) {
            $batch = DueBatch::query()->firstOrCreate(
                ['due_plan_id' => $duePlan->id, 'period' => $period],
                [
                    'apartment_id' => $duePlan->apartment_id,
                    'due_type' => $duePlan->due_type,
                    'category_id' => $category->id,
                    'source_type' => DueBatch::SOURCE_MANUAL,
                    'distribution_type' => $duePlan->distribution_type,
                    'target_audience' => 'tenant_priority',
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

    private function generatePastPeriods(DuePlan $plan, bool $includePast): int
    {
        if (! $plan->is_active || ! $includePast) {
            return 0;
        }

        $currentPeriod = now()->format('Y-m');
        $plan->update(['skipped_periods' => array_values(array_filter(
            $plan->skipped_periods ?? [], fn ($period) => $period >= $currentPeriod
        ))]);
        $total = 0;
        $cursor = $plan->start_date->copy()->startOfMonth();
        $end = $plan->end_date->copy()->startOfMonth();

        while ($cursor <= $end && $cursor->format('Y-m') < $currentPeriod) {
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
            $account = $this->getAccountForPeriod($unit, $periodDate, 'tenant_priority');
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
            return redirect()->route('subscriber.dashboard');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        return $apartment;
    }
}
