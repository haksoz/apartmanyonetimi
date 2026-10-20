<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpensePlan;
use App\Support\CurrentApartment;
use App\Support\ExpensePlanGenerator;
use App\Support\FeatureGate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ExpensePlanController extends Controller
{
    public function __construct(private readonly ExpensePlanGenerator $generator)
    {
    }

    public function create(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        $this->authorizeFeature($apartment);

        $accounts = Account::query()
            ->where('apartment_id', $apartment->id)
            ->where('type', Account::TYPE_SUPPLIER)
            ->where('is_hidden', false)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $categories = Category::query()
            ->where('apartment_id', $apartment->id)
            ->where('type', Category::TYPE_EXPENSE)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('expense-plans.create', compact('apartment', 'accounts', 'categories'));
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        $this->authorizeFeature($apartment);

        $request->mergeIfMissing(['generation_mode' => ExpensePlan::GENERATION_ON_SCHEDULE]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'description_suffix' => ['nullable', 'string', 'max:100'],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('apartment_id', $apartment->id)->where('is_active', true),
            ],
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('apartment_id', $apartment->id)
                    ->where('type', Account::TYPE_SUPPLIER),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required_if:generation_mode,create_all_now', 'nullable', 'date', 'after_or_equal:start_date'],
            'generation_mode' => ['required', Rule::in([ExpensePlan::GENERATION_ON_SCHEDULE, ExpensePlan::GENERATION_CREATE_ALL_NOW])],
            'include_past_periods' => ['sometimes', 'boolean'],
            'generate_day' => ['required', 'integer', 'min:1', 'max:28'],
            'due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],
        ]);

        unset($validated['include_past_periods']);
        [$plan, $message] = DB::transaction(function () use ($validated, $apartment, $request) {
            $plan = ExpensePlan::create([
                ...$validated,
                'apartment_id' => $apartment->id,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return [$plan, 'Gider planı oluşturuldu.'.$this->generateIfRequested($plan, $request)];
        });

        return redirect()->route('expense-plans.show', $plan)
            ->with('status', $message);
    }

    public function show(CurrentApartment $currentApartment, ExpensePlan $expensePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        if ($expensePlan->apartment_id !== $apartment->id) {
            abort(404);
        }
        $this->authorizeFeature($apartment);

        $expensePlan->load(['category', 'account']);
        $periods = $this->buildPeriods($expensePlan);

        return view('expense-plans.show', [
            'apartment' => $apartment,
            'plan' => $expensePlan,
            'periods' => $periods,
        ]);
    }

    public function update(Request $request, CurrentApartment $currentApartment, ExpensePlan $expensePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        if ($expensePlan->apartment_id !== $apartment->id) {
            abort(404);
        }
        $this->authorizeFeature($apartment);

        $request->mergeIfMissing(['generation_mode' => $expensePlan->generation_mode ?: ExpensePlan::GENERATION_ON_SCHEDULE]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'description_suffix' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'end_date' => ['required_if:generation_mode,create_all_now', 'nullable', 'date', 'after_or_equal:'.$expensePlan->start_date->toDateString()],
            'generation_mode' => ['required', Rule::in([ExpensePlan::GENERATION_ON_SCHEDULE, ExpensePlan::GENERATION_CREATE_ALL_NOW])],
            'include_past_periods' => ['sometimes', 'boolean'],
            'generate_day' => ['required', 'integer', 'min:1', 'max:28'],
            'due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],
        ]);

        unset($validated['include_past_periods']);
        $message = DB::transaction(function () use ($expensePlan, $validated, $request) {
            $expensePlan->update([
                ...$validated,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return 'Gider planı güncellendi. Mevcut gider kayıtları değişmedi.'.$this->generateIfRequested($expensePlan, $request);
        });

        return redirect()->route('expense-plans.show', $expensePlan)
            ->with('status', $message);
    }

    private function generateIfRequested(ExpensePlan $plan, Request $request): string
    {
        if ($plan->generation_mode !== ExpensePlan::GENERATION_CREATE_ALL_NOW) {
            return '';
        }
        if (! $plan->is_active) {
            return ' Plan pasif olduğu için gider oluşturulmadı.';
        }

        $result = $this->generator->generateAll($plan, $request->boolean('include_past_periods'));

        return " {$result['created']} gider oluşturuldu. {$result['existing']} mevcut dönem atlandı. {$result['excluded']} geçmiş dönem dahil edilmedi.";
    }

    public function deactivate(CurrentApartment $currentApartment, ExpensePlan $expensePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        if ($expensePlan->apartment_id !== $apartment->id) {
            abort(404);
        }
        $this->authorizeFeature($apartment);

        $expensePlan->update(['is_active' => false]);

        return redirect()->route('expense-plans.show', $expensePlan)
            ->with('status', 'Gider planı durduruldu. Mevcut giderler korundu.');
    }

    public function generateMonth(Request $request, CurrentApartment $currentApartment, ExpensePlan $expensePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        if ($expensePlan->apartment_id !== $apartment->id) {
            abort(404);
        }
        $this->authorizeFeature($apartment);

        if (! $expensePlan->is_active) {
            return redirect()->route('expense-plans.show', $expensePlan)
                ->with('error', 'Pasif plan üzerinden gider oluşturulamaz.');
        }

        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
        ]);

        $count = $this->generator->generateForPeriod($expensePlan, $validated['period']);

        return redirect()->route('expense-plans.show', $expensePlan)
            ->with($count > 0 ? 'status' : 'error', $count > 0
                ? "{$validated['period']} dönemi için gider oluşturuldu."
                : 'Bu dönem için oluşturulacak gider yok veya zaten mevcut.');
    }

    private function buildPeriods(ExpensePlan $plan): array
    {
        $periods = [];
        $cursor = $plan->start_date->copy()->startOfMonth();
        $end = ($plan->end_date ?? now()->addMonths(2))->copy()->startOfMonth();
        if (! $plan->end_date) {
            $end = max($cursor->copy(), now()->copy()->startOfMonth())->addMonths(2);
        }

        while ($cursor <= $end) {
            $period = $cursor->format('Y-m');
            $key = ExpensePlan::occurrenceKey($plan->id, $period);
            $expense = Expense::query()->where('plan_occurrence_key', $key)->first();

            if ($expense) {
                $status = 'complete';
            } elseif (! $plan->is_active) {
                $status = 'stopped';
            } else {
                $status = 'not_generated';
            }

            $periods[] = [
                'period' => $period,
                'label' => $cursor->copy()->locale('tr')->isoFormat('MMMM YYYY'),
                'status' => $status,
                'expense_id' => $expense?->id,
            ];
            $cursor->addMonth();
        }

        return $periods;
    }

    private function authorizeFeature($apartment): void
    {
        if (! FeatureGate::allows($apartment, 'expense_plans') && ! auth()->user()?->isAdmin()) {
            abort(403, 'Gider planlama özelliği bu apartman için aktif değil.');
        }
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
