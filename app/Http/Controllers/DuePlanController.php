<?php

namespace App\Http\Controllers;

use App\Enums\DueType;
use App\Models\Category;
use App\Models\AccountTransaction;
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
            ->with(['batches' => fn ($q) => $q->withCount('dues')->orderBy('period')])
            ->where('apartment_id', $apartment->id)
            ->orderByDesc('year')
            ->orderBy('name')
            ->get();

        $orphanBatches = DueBatch::query()
            ->withCount('dues')
            ->whereNull('due_plan_id')
            ->where('apartment_id', $apartment->id)
            ->has('dues')
            ->orderBy('period')
            ->get();

        return view('due-plans.index', compact('plans', 'apartment', 'orphanBatches'));
    }

    public function create(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $dueTypes = DueType::options();
        $categories = Category::where('apartment_id', $apartment->id)->whereIn('type', [Category::TYPE_INCOME, Category::TYPE_ALL])->where('is_active', true)->orderBy('name')->get();

        $units = Unit::query()
            ->where('apartment_id', $apartment->id)
            ->orderBy('unit_no')
            ->get(['id', 'unit_no', 'block', 'square_meters', 'share_coefficient']);

        return view('due-plans.create', compact('apartment', 'dueTypes', 'categories', 'units'));
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'year'              => ['required', 'integer', 'min:2000', 'max:2100'],
            'due_type'          => ['required', Rule::in(DueType::values())],
            'category_id'       => ['nullable', 'exists:categories,id'],
            'amount_type'       => ['required', Rule::in(['monthly', 'yearly', 'per_unit'])],
            'monthly_amount'    => ['required_if:amount_type,monthly', 'nullable', 'numeric', 'min:0.01'],
            'yearly_amount'     => ['required_if:amount_type,yearly', 'nullable', 'numeric', 'min:0.01'],
            'per_unit_amount'   => ['required_if:amount_type,per_unit', 'nullable', 'numeric', 'min:0.01'],
            'distribution_type' => ['required', Rule::in(['equal', 'square_meters', 'share_coefficient'])],
            'target_audience'   => ['required', Rule::in(['tenant_priority', 'owner_only'])],
            'due_day'           => ['required', 'integer', 'min:1', 'max:28'],
            'auto_generate'     => ['boolean'],
            'generate_day'      => ['required', 'integer', 'min:1', 'max:28'],
            'description'       => ['nullable', 'string', 'max:255'],
            'is_active'         => ['boolean'],
        ]);

        if (isset($validated['per_unit_amount'])) {
            $validated['per_unit_amount'] = round((float) $validated['per_unit_amount'], 2);
        }

        $duplicatePlan = DuePlan::where('apartment_id', $apartment->id)
            ->where('year', $validated['year'])
            ->where('due_type', $validated['due_type'])
            ->where('category_id', $validated['category_id'] ?? null)
            ->first();

        if ($duplicatePlan) {
            return back()->withInput()->withErrors([
                'year' => "Bu yıl, tür ve kategori kombinasyonu için zaten \"" . $duplicatePlan->name . "\" adında bir plan mevcut.",
            ]);
        }

        if ($request->boolean('auto_generate', false)) {
            $existingAutoPlan = DuePlan::where('apartment_id', $apartment->id)
                ->where('auto_generate', true)
                ->where('is_active', true)
                ->first();

            if ($existingAutoPlan) {
                return back()->withInput()->withErrors([
                    'auto_generate' => "Bu apartmanda zaten aktif bir otomatik aidat planı mevcut: \"{$existingAutoPlan->name}\". Apartman başına yalnızca 1 adet otomatik plan oluşturulabilir. Mevcut planı devre dışı bırakarak yeni plan oluşturabilirsiniz.",
                ]);
            }
        }

        $plan = DuePlan::create(array_merge($validated, [
            'apartment_id'  => $apartment->id,
            'is_active'     => $request->boolean('is_active', true),
            'auto_generate' => $request->boolean('auto_generate', false),
        ]));

        if ($request->boolean('auto_generate', false) && $request->boolean('start_this_month', false)) {
            $this->createDuesForPeriod($plan, now()->format('Y-m'));
        }

        $message = $request->boolean('auto_generate', false)
            ? 'Aidat planı oluşturuldu. Sistem her ay belirlenen günde aidatı otomatik oluşturacaktır.'
            : 'Aidat planı oluşturuldu. Aidatlar bölümünden bu plana göre aidat borçlandırması yapabilirsiniz.';

        return redirect()->route('due-plans.index')->with('status', $message);
    }

    public function edit(CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($duePlan->apartment_id !== $apartment->id) abort(404);

        $dueTypes = DueType::options();
        $categories = Category::where('apartment_id', $apartment->id)->whereIn('type', [Category::TYPE_INCOME, Category::TYPE_ALL])->where('is_active', true)->orderBy('name')->get();

        $units = Unit::query()
            ->where('apartment_id', $apartment->id)
            ->orderBy('unit_no')
            ->get(['id', 'unit_no', 'block', 'square_meters', 'share_coefficient']);

        return view('due-plans.edit', compact('duePlan', 'dueTypes', 'categories', 'units'));
    }

    public function update(Request $request, CurrentApartment $currentApartment, DuePlan $duePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($duePlan->apartment_id !== $apartment->id) abort(404);

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:255'],
            'year'              => ['required', 'integer', 'min:2000', 'max:2100'],
            'due_type'          => ['required', Rule::in(DueType::values())],
            'category_id'       => ['nullable', 'exists:categories,id'],
            'amount_type'       => ['required', Rule::in(['monthly', 'yearly', 'per_unit'])],
            'monthly_amount'    => ['required_if:amount_type,monthly', 'nullable', 'numeric', 'min:0.01'],
            'yearly_amount'     => ['required_if:amount_type,yearly', 'nullable', 'numeric', 'min:0.01'],
            'per_unit_amount'   => ['required_if:amount_type,per_unit', 'nullable', 'numeric', 'min:0.01'],
            'distribution_type' => ['required', Rule::in(['equal', 'square_meters', 'share_coefficient'])],
            'target_audience'   => ['required', Rule::in(['tenant_priority', 'owner_only'])],
            'due_day'           => ['required', 'integer', 'min:1', 'max:28'],
            'auto_generate'     => ['boolean'],
            'generate_day'      => ['required', 'integer', 'min:1', 'max:28'],
            'description'       => ['nullable', 'string', 'max:255'],
        ]);

        if (isset($validated['per_unit_amount'])) {
            $validated['per_unit_amount'] = round((float) $validated['per_unit_amount'], 2);
        }

        $duplicatePlan = DuePlan::where('apartment_id', $apartment->id)
            ->where('year', $validated['year'])
            ->where('due_type', $validated['due_type'])
            ->where('category_id', $validated['category_id'] ?? null)
            ->where('id', '!=', $duePlan->id)
            ->first();

        if ($duplicatePlan) {
            return back()->withInput()->withErrors([
                'year' => "Bu yıl, tür ve kategori kombinasyonu için zaten \"" . $duplicatePlan->name . "\" adında bir plan mevcut.",
            ]);
        }

        $duePlan->update(array_merge($validated, [
            'is_active'     => $request->boolean('is_active', true),
            'auto_generate' => $request->boolean('auto_generate', false),
        ]));

        return redirect()->route('due-plans.index')->with('status', 'Aidat planı güncellendi.');
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
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) return $apartment;
        if ($duePlan->apartment_id !== $apartment->id) abort(404);

        $duePlan->update(['is_active' => false, 'auto_generate' => false]);

        return redirect()->route('due-plans.index')->with('status', "\"" . $duePlan->name . "\" planı pasife alındı.");
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

        if ($duePlan->isGeneratedForPeriod($period)) {
            $existingBatch = $duePlan->batches()->where('period', $period)->whereHas('dues')->first();
            $duesUrl = $existingBatch
                ? route('dues.index', ['batch_id' => $existingBatch->id])
                : route('dues.index');
            return redirect()->route('dues.index')
                ->with('error_html', "{$period} dönemi için bu plan kapsamında aidat zaten oluşturulmuş. Yeniden oluşturmak istiyorsanız önce mevcut aidatları silmeniz gerekir. <a href=\"{$duesUrl}\" class=\"underline font-semibold\">Mevcut aidatları görüntüle →</a>");
        }

        $count = $this->createDuesForPeriod($duePlan, $period, $validated['description'] ?? null);

        if ($count === 0) {
            return redirect()->route('due-plans.index')->with('error', 'Aidatlandırılacak daire bulunamadı.');
        }

        return redirect()->route('dues.index')
            ->with('status', "{$period} dönemi için {$count} daireye aidat oluşturuldu.");
    }

    public function createDuesForPeriod(DuePlan $duePlan, string $period, ?string $customDescription = null): int
    {
        $periodDate    = Carbon::parse($period . '-01');
        $dueDate       = $periodDate->copy()->setDay(min($duePlan->due_day, $periodDate->daysInMonth));
        $createdAtDate = $periodDate->startOfMonth()->toDateString();
        $monthlyAmount = $duePlan->monthly_amount_resolved;

        $units = Unit::query()
            ->with(['ownerAccount', 'accounts'])
            ->where('apartment_id', $duePlan->apartment_id)
            ->orderBy('unit_no')
            ->get();

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
            return 0;
        }

        $turkishMonths = [
            1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan',
            5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos',
            9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık',
        ];
        $monthName        = $turkishMonths[(int) $periodDate->format('n')];
        $planLabel        = $duePlan->due_type_label !== '-' ? $duePlan->due_type_label : $duePlan->name;
        $batchDescription = $customDescription ?: "{$monthName} {$periodDate->year} - {$planLabel}";

        DB::transaction(function () use ($duePlan, $period, $monthlyAmount, $dueDate, $periodDate, $unitAccounts, $totalWeight, $batchDescription) {
            $createdAtDate = Carbon::parse($period . '-01')->startOfMonth()->toDateString();
            $batch = DueBatch::create([
                'apartment_id'      => $duePlan->apartment_id,
                'due_plan_id'       => $duePlan->id,
                'due_type'          => $duePlan->due_type,
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
                    'apartment_id'      => $duePlan->apartment_id,
                    'due_batch_id'      => $batch->id,
                    'unit_id'           => $item['unit']->id,
                    'account_id'        => $item['account']->id,
                    'due_type'          => $duePlan->due_type,
                    'category_id'       => $duePlan->category_id,
                    'period'            => $period,
                    'amount'            => $amount,
                    'remaining_amount'  => $amount,
                    'due_date'          => $dueDate,
                    'status'            => 'unpaid',
                    'description'       => $batchDescription,
                    'created_at_manual' => $createdAtDate,
                ]);

                AccountTransaction::create([
                    'apartment_id'         => $duePlan->apartment_id,
                    'account_id'           => $item['account']->id,
                    'transactionable_type' => Due::class,
                    'transactionable_id'   => $due->id,
                    'type'                 => 'debit',
                    'description'          => $batchDescription,
                    'amount'               => $amount,
                    'transaction_date'     => $periodDate->startOfMonth()->toDateString(),
                ]);
            }
        });

        return count($unitAccounts);
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
