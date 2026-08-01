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

    public function index(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $plan = DuePlan::query()
            ->with(['batches' => fn ($q) => $q->withCount('dues')->orderBy('period')])
            ->where('apartment_id', $apartment->id)
            ->orderByDesc('id')
            ->firstOrNew();

        $plan->apartment_id = $apartment->id;

        $units = Unit::query()
            ->where('apartment_id', $apartment->id)
            ->orderBy('unit_no')
            ->get(['id', 'unit_no', 'block', 'square_meters', 'share_coefficient']);

        $periods = $this->buildPeriodsList($plan, $units);

        $currentPeriod = now()->format('Y-m');
        $shouldPromptGenerate = session('prompt_generate_period') === $currentPeriod
            && $plan->exists
            && $plan->is_active;

        return view('due-plans.index', compact('plan', 'apartment', 'units', 'periods', 'currentPeriod', 'shouldPromptGenerate'));
    }

    private function buildPeriodsList(DuePlan $plan, $units): array
    {
        if (! $plan->exists || ! $plan->start_date || ! $plan->end_date) {
            return [];
        }

        $periods = [];
        $cursor  = $plan->start_date->copy()->startOfMonth();
        $end     = $plan->end_date->copy()->startOfMonth();

        while ($cursor <= $end) {
            $period      = $cursor->format('Y-m');
            $periodDate  = $cursor->copy();
            $batch = $plan->batches->firstWhere('period', $period);
            $reconciliation = $this->aidatReconciliation->reconcile($plan, $period);
            $activeCount = count($reconciliation['completed_account_ids']);
            $expectedCount = $reconciliation['target_accounts']->count();

            if ($activeCount === 0) {
                $status = 'not_generated';
            } elseif ($activeCount < $expectedCount) {
                $status = 'incomplete';
            } else {
                $status = 'complete';
            }

            $periods[] = [
                'period'         => $period,
                'label'          => $periodDate->locale('tr')->isoFormat('MMMM YYYY'),
                'status'         => $status,
                'active_count'   => $activeCount,
                'expected_count' => $expectedCount,
                'batch_id'       => $batch?->id,
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
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($duePlan->apartment_id !== $apartment->id) abort(404);

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);

        $period = $validated['period'];
        $periodDate = Carbon::parse($period . '-01');

        if (
            $periodDate->copy()->endOfMonth()->toDateString() < $duePlan->start_date->toDateString()
            || $periodDate->copy()->startOfMonth()->toDateString() > $duePlan->end_date->toDateString()
        ) {
            return redirect()->route('due-plans.index')
                ->with('error', 'Seçilen dönem planın başlangıç/bitiş tarihleri arasında değil.');
        }

        $createdCount = $this->createDuesForPeriod($duePlan, $period);

        if ($createdCount === 0) {
            return redirect()->route('due-plans.index')
                ->with('status', 'Seçilen dönemde eksik aidat bulunmuyor.');
        }

        return redirect()->route('due-plans.index')
            ->with('status', "{$period} dönemi için {$createdCount} eksik aidat oluşturuldu.");
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $validated = $request->validate([
            'start_date'        => ['required', 'date'],
            'end_date'          => ['required', 'date', 'after_or_equal:start_date'],
            'monthly_amount'    => ['required', 'numeric', 'min:0.01'],
            'distribution_type' => ['required', Rule::in(['equal', 'square_meters', 'share_coefficient'])],
            'target_audience'   => ['required', Rule::in(['tenant_priority', 'owner_only'])],
            'due_day'           => ['required', 'integer', 'min:1', 'max:28'],
            'generate_day'      => ['required', 'integer', 'min:1', 'max:28'],
            'description'       => ['nullable', 'string', 'max:255'],
            'is_active'         => ['boolean'],
        ]);

        $validated = array_merge($validated, [
            'apartment_id'  => $apartment->id,
            'name'          => 'Aidat Kararı',
            'amount_type'   => 'monthly',
            'due_type'      => 'aidat',
            'category_id'   => $this->aidatReconciliation->categoryFor($apartment)->id,
            'auto_generate' => true,
            'is_active'     => $request->boolean('is_active', true),
        ]);

        $plan = DuePlan::query()->where('apartment_id', $apartment->id)->first();

        if ($plan) {
            $plan->update($validated);
        } else {
            $plan = DuePlan::create($validated);
        }

        // Aynı apartmanda başka plan kalmasın
        DuePlan::where('apartment_id', $apartment->id)
            ->where('id', '!=', $plan->id)
            ->delete();

        $currentPeriod = now()->format('Y-m');
        $today         = now()->toDateString();

        if (
            $plan->is_active
            && $today >= $plan->start_date->toDateString()
            && $today <= $plan->end_date->toDateString()
            && ! $plan->isGeneratedForPeriod($currentPeriod)
        ) {
            return redirect()->route('due-plans.index')
                ->with('prompt_generate_period', $currentPeriod)
                ->with('prompt_generate_plan_id', $plan->id)
                ->with('status', 'Aidat kararı ayarları kaydedildi.');
        }

        return redirect()->route('due-plans.index')->with('status', 'Aidat kararı ayarları kaydedildi.');
    }

    public function edit(CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        return redirect()->route('due-plans.index');
    }

    public function update(Request $request, CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        return redirect()->route('due-plans.index');
    }

    public function destroy(CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($duePlan->apartment_id !== $apartment->id) abort(404);

        $duePlan->delete();

        return redirect()->route('due-plans.index')->with('status', 'Aidat planı silindi.');
    }

    public function deactivate(CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        return redirect()->route('due-plans.index');
    }

    public function generateMonth(Request $request, CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($duePlan->apartment_id !== $apartment->id) abort(404);

        $validated = $request->validate([
            'period'      => ['required', 'date_format:Y-m'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $period = $validated['period'];

        $periodDate = Carbon::parse($period . '-01');
        if (
            $periodDate->copy()->endOfMonth()->toDateString() < $duePlan->start_date->toDateString()
            || $periodDate->copy()->startOfMonth()->toDateString() > $duePlan->end_date->toDateString()
        ) {
            return redirect()->route('due-plans.index')
                ->with('error', 'Seçilen dönem planın başlangıç/bitiş tarihleri arasında değil.');
        }

        $count = $this->createDuesForPeriod($duePlan, $period, $validated['description'] ?? null);

        if ($count === 0) {
            return redirect()->route('due-plans.index')->with('error', 'Seçilen dönem için oluşturulacak eksik aidat bulunmuyor.');
        }

        return redirect()->route('dues.index')
            ->with('status', "{$period} dönemi için {$count} daireye aidat oluşturuldu.");
    }

    public function createDuesForPeriod(DuePlan $duePlan, string $period, ?string $customDescription = null, bool $allowPartial = true): int
    {
        $periodDate = Carbon::parse($period . '-01');

        if (
            $periodDate->copy()->endOfMonth()->toDateString() < $duePlan->start_date->toDateString()
            || $periodDate->copy()->startOfMonth()->toDateString() > $duePlan->end_date->toDateString()
        ) {
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
        $monthName = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'][(int) $periodDate->format('n')];
        $batchDescription = $customDescription ?: "{$periodDate->year} {$monthName} - Aidat";
        $count = count($unitAccounts);
        $allocated = 0;
        $snapshot = [];

        foreach ($unitAccounts as $index => $item) {
            $isLast = $index === $count - 1;
            $amount = $duePlan->distribution_type === DuePlan::DISTRIBUTION_EQUAL
                ? ($isLast ? round($monthlyAmount - $allocated, 2) : round($monthlyAmount / $count, 2))
                : ($isLast ? round($monthlyAmount - $allocated, 2) : round($monthlyAmount * $item['weight'] / $totalWeight, 2));
            $allocated += $amount;
            $snapshot[$item['unit']->id] = $amount;
        }

        DB::transaction(function () use ($duePlan, $period, $periodDate, $dueDate, $monthlyAmount, $batchDescription, $snapshot, $unitAccounts, $category) {
            $batch = DueBatch::query()->firstOrCreate(
                ['due_plan_id' => $duePlan->id, 'period' => $period],
                [
                    'apartment_id' => $duePlan->apartment_id,
                    'due_type' => $duePlan->due_type,
                    'category_id' => $category->id,
                    'source_type' => DueBatch::SOURCE_MANUAL,
                    'distribution_type' => $duePlan->distribution_type,
                    'target_audience' => $duePlan->target_audience,
                    'source_amount' => $monthlyAmount,
                    'description' => $batchDescription,
                    'distribution_snapshot' => $snapshot,
                    'created_by' => auth()->id(),
                ],
            );

            $batch->update([
                'category_id' => $category->id,
                'source_amount' => $monthlyAmount,
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
        $totalWeight  = 0;

        foreach ($units as $unit) {
            $account = $this->getAccountForPeriod($unit, $periodDate, $duePlan->target_audience);
            if (! $account) {
                continue;
            }

            $weight = match ($duePlan->distribution_type) {
                DuePlan::DISTRIBUTION_SQUARE_METERS     => (float) ($unit->square_meters ?? 0),
                DuePlan::DISTRIBUTION_SHARE_COEFFICIENT => (float) ($unit->share_coefficient ?? 0),
                default                                  => 1,
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
