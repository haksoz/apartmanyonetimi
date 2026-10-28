<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\Category;
use App\Models\Expense;
use App\Models\ExpensePlan;
use App\Support\CurrentApartment;
use App\Support\ExpensePlanGenerator;
use App\Support\ExpensePlanSummary;
use App\Support\FeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExpensePlanController extends Controller
{
    public function __construct(private readonly ExpensePlanGenerator $generator) {}

    public function create(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof RedirectResponse) {
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
            ->where('allow_expense', true)
            ->where('is_active', true)
            ->orderedForDisplay()
            ->get();

        return view('expense-plans.create', compact('apartment', 'accounts', 'categories'));
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof RedirectResponse) {
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
                Rule::exists('categories', 'id')->where('apartment_id', $apartment->id)->where('allow_expense', true)->where('is_active', true),
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

        return $this->preview($request, $apartment, $validated);
    }

    public function show(CurrentApartment $currentApartment, ExpensePlan $expensePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof RedirectResponse) {
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
        if ($apartment instanceof RedirectResponse) {
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

        return $this->preview($request, $apartment, $validated, $expensePlan);
    }

    private function preview(Request $request, $apartment, array $input, ?ExpensePlan $plan = null)
    {
        unset($input['include_past_periods']);
        $input['is_active'] = $request->boolean('is_active', true);
        $pending = [
            'token' => (string) Str::uuid(), 'apartment_id' => $apartment->id,
            'user_id' => auth()->id(), 'plan_id' => $plan?->id, 'input' => $input,
            'include_past' => false, 'current_action' => null,
        ];

        return $this->confirmationView($request, $pending, $plan);
    }

    private function confirmationView(Request $request, array $pending, ?ExpensePlan $plan, bool $changed = false)
    {
        $proposed = $plan ? clone $plan : new ExpensePlan(['apartment_id' => $pending['apartment_id']]);
        $proposed->fill($pending['input']);
        $proposed->load(['category', 'account']);
        $summary = app(ExpensePlanSummary::class)->build($proposed, $pending['include_past'], $pending['current_action']);
        $pending['token'] = (string) Str::uuid();
        $pending['fingerprint'] = hash('sha256', json_encode($summary));
        $request->session()->put('expense_plan_confirmation', $pending);

        return view('expense-plans.confirm', [
            'proposed' => $proposed, 'summary' => $summary, 'changed' => $changed, 'token' => $pending['token'],
        ]);
    }

    public function confirm(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof RedirectResponse) {
            return $apartment;
        }
        $this->authorizeFeature($apartment);
        $pending = $request->session()->get('expense_plan_confirmation');
        abort_unless($pending && is_string($request->input('confirmation_token'))
            && hash_equals($pending['token'], $request->input('confirmation_token'))
            && $pending['apartment_id'] === $apartment->id && $pending['user_id'] === auth()->id(), 419);
        $plan = $pending['plan_id'] ? ExpensePlan::where('apartment_id', $apartment->id)->findOrFail($pending['plan_id']) : null;
        $back = $plan ? route('expense-plans.show', ['expensePlan' => $plan, 'edit' => 1]) : route('expense-plans.create');
        if ($request->boolean('return_to_form')) {
            $request->session()->forget('expense_plan_confirmation');

            return redirect($back)->withInput($pending['input']);
        }
        $decisions = $request->validate([
            'include_past_periods' => ['sometimes', 'boolean'],
            'current_month_action' => ['nullable', Rule::in(['create', 'skip'])],
        ]);
        $pending['include_past'] = (bool) ($decisions['include_past_periods'] ?? $pending['include_past']);
        $pending['current_action'] = $decisions['current_month_action'] ?? $pending['current_action'];

        return DB::transaction(function () use ($request, $apartment, $pending, $plan) {
            // Serialize confirmations and scheduled generation against the same plan.
            Apartment::whereKey($apartment->id)->lockForUpdate()->firstOrFail();
            $locked = $plan ? ExpensePlan::whereKey($plan->id)->lockForUpdate()->firstOrFail() : new ExpensePlan(['apartment_id' => $apartment->id]);
            $proposed = clone $locked;
            $proposed->fill($pending['input']);
            $proposed->load(['category', 'account']);
            $summary = app(ExpensePlanSummary::class)->build($proposed, $pending['include_past'], $pending['current_action']);
            $changed = ! hash_equals($pending['fingerprint'], hash('sha256', json_encode($summary)));
            if ($changed || $request->boolean('update_summary') || ($summary['current_eligible'] && $pending['current_action'] === null)) {
                return $this->confirmationView($request, $pending, $locked->exists ? $locked : null, $changed);
            }
            // Validate referenced records again immediately before writing.
            abort_unless(Account::where('apartment_id', $apartment->id)->where('type', Account::TYPE_SUPPLIER)->whereKey($proposed->account_id)->exists(), 422);
            if (! $locked->exists || $locked->category_id !== $proposed->category_id) {
                abort_unless(Category::where('apartment_id', $apartment->id)->where('allow_expense', true)->where('is_active', true)->whereKey($proposed->category_id)->exists(), 422);
            }
            $skipped = $locked->skipped_periods ?? [];
            if ($summary['current_eligible']) {
                $skipped = array_values(array_diff($skipped, [now()->format('Y-m')]));
                if ($pending['current_action'] === 'skip') {
                    $skipped[] = now()->format('Y-m');
                }
            }
            $locked->fill($pending['input']);
            $locked->skipped_periods = $skipped;
            $locked->save();
            $created = 0;
            foreach ($summary['rows'] as $row) {
                if ($row['create']) {
                    $created += $this->generator->generateForPeriod($locked, $row['period']);
                }
            }
            $request->session()->forget('expense_plan_confirmation');

            return redirect()->route('expense-plans.show', $locked)->with('status', "Gider planı kaydedildi. {$created} gider oluşturuldu. {$summary['existing']} mevcut kayıt korundu.");
        });
    }

    public function deactivate(CurrentApartment $currentApartment, ExpensePlan $expensePlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof RedirectResponse) {
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
        if ($apartment instanceof RedirectResponse) {
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
            } elseif (in_array($period, $plan->skipped_periods ?? [], true)) {
                $status = 'skipped';
            } elseif (! $plan->is_active) {
                $status = 'stopped';
            } else {
                $status = 'not_generated';
            }

            $plannedDate = $cursor->copy()->day(min($plan->generate_day, $cursor->daysInMonth));
            $periods[] = [
                'period' => $period,
                'label' => $cursor->copy()->locale('tr')->isoFormat('MMMM YYYY'),
                'status' => $status,
                'expense_id' => $expense?->id,
                'expense_date' => $expense?->expense_date?->format('d.m.Y') ?? $plannedDate->format('d.m.Y'),
                'due_date' => $expense ? $expense->due_date?->format('d.m.Y') : ($plan->due_days !== null ? $plannedDate->copy()->addDays($plan->due_days)->format('d.m.Y') : null),
                'amount' => $expense?->amount ?? $plan->amount,
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
            return redirect()->route('subscriber.dashboard');
        }

        if (! $apartment) {
            return redirect()->route('apartments.create');
        }

        return $apartment;
    }
}
