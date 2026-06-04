<?php

namespace App\Http\Controllers;

use App\Models\AccountTransaction;
use App\Models\Category;
use App\Models\Due;
use App\Models\DueBatch;
use App\Models\DuePlan;
use App\Models\TenantAssignment;
use App\Models\Unit;
use App\Support\CurrentApartment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DuePlanController extends Controller
{
    public function index(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $plans = DuePlan::query()
            ->with(['category', 'batches' => fn ($q) => $q->withCount('dues')->orderBy('period')])
            ->where('apartment_id', $apartment->id)
            ->orderByDesc('year')
            ->orderBy('name')
            ->get();

        return view('due-plans.index', compact('plans', 'apartment'));
    }

    public function create(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $categories = Category::query()
            ->where('apartment_id', $apartment->id)
            ->where('is_active', true)
            ->whereIn('type', ['income', 'all'])
            ->orderBy('name')
            ->get();

        $units = Unit::query()
            ->where('apartment_id', $apartment->id)
            ->orderBy('unit_no')
            ->get(['id', 'unit_no', 'block', 'square_meters', 'share_coefficient']);

        return view('due-plans.create', compact('apartment', 'categories', 'units'));
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'year'              => ['required', 'integer', 'min:2000', 'max:2100'],
            'amount_type'       => ['required', Rule::in(['monthly', 'yearly', 'per_unit'])],
            'monthly_amount'    => ['required_if:amount_type,monthly', 'nullable', 'numeric', 'min:0.01'],
            'yearly_amount'     => ['required_if:amount_type,yearly', 'nullable', 'numeric', 'min:0.01'],
            'per_unit_amount'   => ['required_if:amount_type,per_unit', 'nullable', 'numeric', 'min:0.01'],
            'distribution_type' => ['required', Rule::in(['equal', 'square_meters', 'share_coefficient'])],
            'target_audience'   => ['required', Rule::in(['tenant_priority', 'owner_only'])],
            'category_id'       => ['nullable', 'integer', Rule::exists('categories', 'id')->where('apartment_id', $apartment->id)],
            'due_day'           => ['required', 'integer', 'min:1', 'max:28'],
            'description'       => ['nullable', 'string', 'max:255'],
            'is_active'         => ['boolean'],
        ]);

        if (isset($validated['per_unit_amount'])) {
            $validated['per_unit_amount'] = round((float) $validated['per_unit_amount'], 2);
        }

        DuePlan::create(array_merge($validated, [
            'apartment_id' => $apartment->id,
            'is_active'    => $request->boolean('is_active', true),
        ]));

        return redirect()->route('due-plans.index')->with('status', 'Aidat planı oluşturuldu.');
    }

    public function edit(CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($duePlan->apartment_id !== $apartment->id) abort(404);

        $categories = Category::query()
            ->where('apartment_id', $apartment->id)
            ->where('is_active', true)
            ->whereIn('type', ['income', 'all'])
            ->orderBy('name')
            ->get();

        $units = Unit::query()
            ->where('apartment_id', $apartment->id)
            ->orderBy('unit_no')
            ->get(['id', 'unit_no', 'block', 'square_meters', 'share_coefficient']);

        return view('due-plans.edit', compact('duePlan', 'categories', 'units'));
    }

    public function update(Request $request, CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($duePlan->apartment_id !== $apartment->id) abort(404);

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'year'              => ['required', 'integer', 'min:2000', 'max:2100'],
            'amount_type'       => ['required', Rule::in(['monthly', 'yearly', 'per_unit'])],
            'monthly_amount'    => ['required_if:amount_type,monthly', 'nullable', 'numeric', 'min:0.01'],
            'yearly_amount'     => ['required_if:amount_type,yearly', 'nullable', 'numeric', 'min:0.01'],
            'per_unit_amount'   => ['required_if:amount_type,per_unit', 'nullable', 'numeric', 'min:0.01'],
            'distribution_type' => ['required', Rule::in(['equal', 'square_meters', 'share_coefficient'])],
            'target_audience'   => ['required', Rule::in(['tenant_priority', 'owner_only'])],
            'category_id'       => ['nullable', 'integer', Rule::exists('categories', 'id')->where('apartment_id', $apartment->id)],
            'due_day'           => ['required', 'integer', 'min:1', 'max:28'],
            'description'       => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($validated['per_unit_amount'])) {
            $validated['per_unit_amount'] = round((float) $validated['per_unit_amount'], 2);
        }

        $duePlan->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active', true),
        ]));

        return redirect()->route('due-plans.index')->with('status', 'Aidat planı güncellendi.');
    }

    public function destroy(CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($duePlan->apartment_id !== $apartment->id) abort(404);

        if ($duePlan->batches()->exists()) {
            return redirect()->route('due-plans.index')->with('error', 'Bu plana ait aidatlandırma kayıtları olduğu için silinemez.');
        }

        $duePlan->delete();

        return redirect()->route('due-plans.index')->with('status', 'Aidat planı silindi.');
    }

    public function generateMonth(Request $request, CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($duePlan->apartment_id !== $apartment->id) abort(404);

        $validated = $request->validate([
            'period'      => ['required', 'date_format:Y-m'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $period = $validated['period'];

        // Çakışma kontrolü: bu plan bu dönem için zaten oluşturulmuş mu?
        if ($duePlan->isGeneratedForPeriod($period)) {
            $existingBatch = $duePlan->batches()->where('period', $period)->whereHas('dues')->first();
            $duesUrl = $existingBatch
                ? route('dues.index', ['batch_id' => $existingBatch->id])
                : route('dues.index');
            return redirect()->route('dues.index')
                ->with('error_html', "{$period} dönemi için bu plan kapsamında aidat zaten oluşturulmuş. Yeniden oluşturmak istiyorsanız önce mevcut aidatları silmeniz gerekir. <a href=\"{$duesUrl}\" class=\"underline font-semibold\">Mevcut aidatları görüntüle →</a>");
        }

        $monthlyAmount = $duePlan->monthly_amount_resolved;
        $periodDate    = Carbon::parse($period . '-01');
        $dueDate       = $periodDate->copy()->setDay(min($duePlan->due_day, $periodDate->daysInMonth));

        $units = Unit::query()
            ->with(['ownerAccount', 'accounts'])
            ->where('apartment_id', $apartment->id)
            ->orderBy('unit_no')
            ->get();

        // Dağıtım tipine göre birim ağırlıklarını hesapla
        $unitAccounts = [];
        $totalWeight  = 0;

        foreach ($units as $unit) {
            $account = $this->getAccountForPeriod($unit, $periodDate, $duePlan->target_audience);
            if (! $account) continue;

            $weight = match ($duePlan->distribution_type) {
                DuePlan::DISTRIBUTION_SQUARE_METERS     => (float) ($unit->square_meters ?? 0),
                DuePlan::DISTRIBUTION_SHARE_COEFFICIENT => (float) ($unit->share_coefficient ?? 0),
                default                                  => 1,
            };

            if ($weight <= 0 && $duePlan->distribution_type !== DuePlan::DISTRIBUTION_EQUAL) continue;

            $unitAccounts[] = ['unit' => $unit, 'account' => $account, 'weight' => $weight];
            $totalWeight += $weight;
        }

        if (empty($unitAccounts)) {
            return redirect()->route('due-plans.index')->with('error', 'Aidatlandırılacak daire bulunamadı.');
        }

        $batchDescription = $validated['description'];

        DB::transaction(function () use ($duePlan, $apartment, $period, $monthlyAmount, $dueDate, $periodDate, $unitAccounts, $totalWeight, $batchDescription) {

            $batch = DueBatch::create([
                'apartment_id'      => $apartment->id,
                'due_plan_id'       => $duePlan->id,
                'category_id'       => $duePlan->category_id,
                'source_type'       => DueBatch::SOURCE_MANUAL,
                'distribution_type' => $duePlan->distribution_type,
                'target_audience'   => $duePlan->target_audience,
                'period'            => $period,
                'source_amount'     => $monthlyAmount,
                'description'       => $batchDescription,
                'created_by'        => auth()->id(),
            ]);

            $count     = count($unitAccounts);
            $allocated = 0;
            $isPerUnit = $duePlan->amount_type === DuePlan::AMOUNT_TYPE_PER_UNIT;

            foreach ($unitAccounts as $index => $item) {
                $isLast = $index === $count - 1;

                if ($isPerUnit) {
                    // Daire başı sabit tutar — her daireye aynı miktar
                    $amount = (float) number_format((float) $duePlan->per_unit_amount, 2, '.', '');
                } elseif ($duePlan->distribution_type === DuePlan::DISTRIBUTION_EQUAL) {
                    $amount = $isLast
                        ? round($monthlyAmount - $allocated, 2)
                        : round($monthlyAmount / $count, 2);
                } else {
                    $amount = $isLast
                        ? round($monthlyAmount - $allocated, 2)
                        : round($monthlyAmount * $item['weight'] / $totalWeight, 2);
                }

                $allocated += $amount;

                $due = Due::create([
                    'apartment_id'     => $apartment->id,
                    'due_batch_id'     => $batch->id,
                    'unit_id'          => $item['unit']->id,
                    'account_id'       => $item['account']->id,
                    'category_id'      => $duePlan->category_id,
                    'period'           => $period,
                    'amount'           => $amount,
                    'remaining_amount' => $amount,
                    'due_date'         => $dueDate,
                    'status'           => 'unpaid',
                    'description'      => $batchDescription,
                    'created_at_manual' => $periodDate->startOfMonth()->toDateString(),
                ]);

                AccountTransaction::create([
                    'apartment_id'       => $apartment->id,
                    'account_id'         => $item['account']->id,
                    'transactionable_type' => Due::class,
                    'transactionable_id' => $due->id,
                    'type'               => 'debit',
                    'description'        => $batchDescription,
                    'amount'             => $amount,
                    'transaction_date'   => $periodDate->startOfMonth()->toDateString(),
                ]);
            }
        });

        $count = count($unitAccounts);

        return redirect()->route('dues.index')
            ->with('status', "{$period} dönemi için {$count} daireye aidat oluşturuldu.");
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
