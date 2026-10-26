<?php

namespace App\Http\Controllers;

use App\Enums\DueType;
use App\Models\Apartment;
use App\Models\AssessmentInstallment;
use App\Models\AssessmentPlan;
use App\Models\Category;
use App\Models\DuePlan;
use App\Support\AssessmentPlanGenerator;
use App\Support\AssessmentPlanSummary;
use App\Support\AssessmentWorkCategory;
use App\Support\CurrentApartment;
use App\Support\FeatureGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AssessmentPlanController extends Controller
{
    public function __construct(private readonly AssessmentPlanGenerator $generator) {}

    public function create(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof RedirectResponse) {
            return $apartment;
        }
        $this->authorizeFeature($apartment);

        $categories = Category::where('apartment_id', $apartment->id)
            ->where(fn ($query) => $query->where('allow_malik_payi', true)->orWhere('allow_masraf_yansitma', true))
            ->where('is_active', true)->orderedForDisplay()->get();
        $categoryNames = Category::withTrashed()->where('apartment_id', $apartment->id)
            ->get(['id', 'name', 'is_active', 'deleted_at', 'allow_malik_payi', 'allow_masraf_yansitma']);

        return view('assessment-plans.create', compact('apartment', 'categories', 'categoryNames'));
    }

    public function previewDistribution(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof RedirectResponse) {
            return $apartment;
        }
        $this->authorizeFeature($apartment);
        $input = $request->validate([
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'installment_count' => ['required', 'integer', 'min:1', 'max:60'],
            'distribution_type' => ['required', Rule::in([
                DuePlan::DISTRIBUTION_EQUAL, DuePlan::DISTRIBUTION_SQUARE_METERS, DuePlan::DISTRIBUTION_SHARE_COEFFICIENT,
            ])],
        ]);

        return response()->json($this->generator->groupedPreview(new AssessmentPlan([
            ...$input, 'apartment_id' => $apartment->id,
        ])));
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof RedirectResponse) {
            return $apartment;
        }
        $this->authorizeFeature($apartment);

        $request->mergeIfMissing(['category_mode' => 'new', 'category_name' => $request->input('name')]);
        if (is_string($request->input('category_name'))) {
            $request->merge(['category_name' => trim($request->input('category_name'))]);
        }
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'due_type' => ['required', Rule::in(array_column(AssessmentPlan::debtTypes(), 'value'))],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'distribution_type' => ['required', Rule::in([
                DuePlan::DISTRIBUTION_EQUAL,
                DuePlan::DISTRIBUTION_SQUARE_METERS,
                DuePlan::DISTRIBUTION_SHARE_COEFFICIENT,
            ])],
            'target_audience' => ['required', Rule::in(['tenant_priority', 'owner_only'])],
            'category_mode' => ['required', Rule::in(['new', 'existing'])],
            'category_name' => ['exclude_unless:category_mode,new', 'required', 'string', 'max:255'],
            'category_id' => ['exclude_unless:category_mode,existing', 'required', 'integer',
                Rule::exists('categories', 'id')->where('apartment_id', $apartment->id)
                    ->where('allow_'.$request->input('due_type'), true)->where('is_active', true)->whereNull('deleted_at')],
            'installment_count' => ['required', 'integer', 'min:1', 'max:60'],
            'start_period' => ['required', 'date_format:Y-m'],
            'due_day' => ['required', 'integer', 'min:1', 'max:28'],
            'due_interval_months' => ['required', 'integer', 'min:1', 'max:12'],
            'generation_mode' => ['required', Rule::in([
                AssessmentPlan::GENERATION_CREATE_ALL_NOW,
                AssessmentPlan::GENERATION_ON_SCHEDULE,
            ])],
            'generate_day' => ['required', 'integer', 'min:1', 'max:28'],
            'include_past_installments' => ['nullable', 'boolean'],
            'is_active' => ['boolean'],
        ]);

        return $this->preview($request, $apartment, $validated);
    }

    public function show(CurrentApartment $currentApartment, AssessmentPlan $assessmentPlan, Request $request)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof RedirectResponse) {
            return $apartment;
        }
        if ($assessmentPlan->apartment_id !== $apartment->id) {
            abort(404);
        }
        $this->authorizeFeature($apartment);

        $assessmentPlan->load([
            'installments.lines.unit',
            'installments.lines.due',
            'unitShares.unit',
            'category',
        ]);

        return view('assessment-plans.show', [
            'apartment' => $apartment,
            'plan' => $assessmentPlan,
        ]);
    }

    public function update(Request $request, CurrentApartment $currentApartment, AssessmentPlan $assessmentPlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof RedirectResponse) {
            return $apartment;
        }
        if ($assessmentPlan->apartment_id !== $apartment->id) {
            abort(404);
        }
        $this->authorizeFeature($apartment);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'generate_day' => ['required', 'integer', 'min:1', 'max:28'],
            'is_active' => ['required', 'boolean'],
        ]);

        return $this->preview($request, $apartment, $validated, $assessmentPlan);
    }

    private function preview(Request $request, Apartment $apartment, array $input, ?AssessmentPlan $plan = null)
    {
        unset($input['include_past_installments']);
        $input['is_active'] = $request->boolean('is_active', true);
        $pending = [
            'apartment_id' => $apartment->id, 'user_id' => auth()->id(), 'plan_id' => $plan?->id,
            'input' => $input, 'include_past' => false, 'current_action' => null,
        ];

        return $this->confirmationView($request, $pending, $plan);
    }

    private function confirmationView(Request $request, array $pending, ?AssessmentPlan $plan, bool $changed = false)
    {
        $proposed = $plan ? clone $plan : new AssessmentPlan(['apartment_id' => $pending['apartment_id'], 'due_type' => DueType::MalikPayi]);
        $proposed->fill($pending['input']);
        $summary = app(AssessmentPlanSummary::class)->build($proposed, $pending['input'], $pending['include_past'], $pending['current_action']);
        $summary['category'] = app(AssessmentWorkCategory::class)->proposal($proposed, $pending['input']);
        $pending['token'] = (string) Str::uuid();
        $pending['fingerprint'] = hash('sha256', json_encode($summary));
        $request->session()->put('assessment_plan_confirmation', $pending);

        return view('assessment-plans.confirm', [
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
        $pending = $request->session()->get('assessment_plan_confirmation');
        abort_unless($pending && is_string($request->input('confirmation_token'))
            && hash_equals($pending['token'], $request->input('confirmation_token'))
            && $pending['apartment_id'] === $apartment->id && $pending['user_id'] === auth()->id(), 419);
        $plan = $pending['plan_id'] ? AssessmentPlan::where('apartment_id', $apartment->id)->findOrFail($pending['plan_id']) : null;
        $back = $plan ? route('assessment-plans.show', ['assessmentPlan' => $plan, 'edit' => 1]) : route('assessment-plans.create');
        if ($request->boolean('return_to_form')) {
            $request->session()->forget('assessment_plan_confirmation');

            return redirect($back)->withInput($pending['input']);
        }
        $decisions = $request->validate([
            'include_past_installments' => ['sometimes', 'boolean'],
            'current_month_action' => ['nullable', Rule::in(['create', 'skip'])],
        ]);
        $pending['include_past'] = (bool) ($decisions['include_past_installments'] ?? $pending['include_past']);
        $pending['current_action'] = $decisions['current_month_action'] ?? $pending['current_action'];

        return DB::transaction(function () use ($request, $apartment, $pending, $plan) {
            Apartment::whereKey($apartment->id)->lockForUpdate()->firstOrFail();
            $locked = $plan ? AssessmentPlan::whereKey($plan->id)->lockForUpdate()->firstOrFail()
                : new AssessmentPlan(['apartment_id' => $apartment->id, 'due_type' => DueType::MalikPayi]);
            $proposed = clone $locked;
            $proposed->fill($pending['input']);
            $summary = app(AssessmentPlanSummary::class)->build($proposed, $pending['input'], $pending['include_past'], $pending['current_action']);
            $summary['category'] = app(AssessmentWorkCategory::class)->proposal($proposed, $pending['input']);
            $changed = ! hash_equals($pending['fingerprint'], hash('sha256', json_encode($summary)));
            if ($changed || $request->boolean('update_summary') || ($summary['current_eligible'] && $pending['current_action'] === null)) {
                return $this->confirmationView($request, $pending, $locked->exists ? $locked : null, $changed);
            }
            $skipped = $locked->skipped_periods ?? [];
            if ($summary['current_eligible']) {
                $skipped = array_values(array_diff($skipped, [now()->format('Y-m')]));
                if ($pending['current_action'] === 'skip') {
                    $skipped[] = now()->format('Y-m');
                }
            }
            $creating = ! $locked->exists;
            $locked->fill($pending['input']);
            if ($creating) {
                $locked->category_id = app(AssessmentWorkCategory::class)->resolve($apartment->id, $summary['category']);
            }
            $locked->skipped_periods = $skipped;
            $locked->save();
            if ($creating) {
                $this->generator->buildDistribution($locked, array_map(fn ($row) => ['due_date' => $row['due_date']], $summary['rows']));
            }
            $installments = $locked->installments()->get()->keyBy('sequence');
            $created = 0;
            foreach ($summary['rows'] as $row) {
                if ($row['selected']) {
                    $created += $this->generator->generateInstallment($locked, $installments[$row['sequence']]);
                }
            }
            $request->session()->forget('assessment_plan_confirmation');

            return redirect()->route('assessment-plans.show', $locked)->with('status', "Taksitli borç planı kaydedildi. {$created} borç kaydı oluşturuldu. Mevcut taksitler ve borçlar korundu.");
        });
    }

    public function deactivate(CurrentApartment $currentApartment, AssessmentPlan $assessmentPlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof RedirectResponse) {
            return $apartment;
        }
        if ($assessmentPlan->apartment_id !== $apartment->id) {
            abort(404);
        }
        $this->authorizeFeature($apartment);

        $assessmentPlan->update(['is_active' => false]);

        return redirect()->route('assessment-plans.show', $assessmentPlan)
            ->with('status', 'Plan durduruldu. Mevcut borçlar korundu.');
    }

    public function previewCancel(CurrentApartment $currentApartment, AssessmentPlan $assessmentPlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof RedirectResponse) {
            return $apartment;
        }
        if ($assessmentPlan->apartment_id !== $apartment->id) {
            abort(404);
        }
        $this->authorizeFeature($apartment);

        $assessmentPlan->load(['installments.lines.due.allocations']);

        $cancellable = [];
        $blocked = [];

        foreach ($assessmentPlan->installments as $installment) {
            if ($installment->isCancelled()) {
                continue;
            }
            if ($installment->due_date->lt(now()->startOfDay()) && $installment->status === AssessmentInstallment::STATUS_GENERATED) {
                // Geçmiş oluşmuş taksitler "gelecek iptali" kapsamında değil; isteğe bağlı: yalnız pending gelecek + oluşmuş gelecek
            }

            $isFuture = $installment->due_date->gte(now()->startOfDay());
            if (! $isFuture && $installment->status !== AssessmentInstallment::STATUS_PENDING) {
                continue;
            }
            if (! $isFuture && $installment->status === AssessmentInstallment::STATUS_PENDING) {
                // geçmiş pending de iptal listesine alınabilir
            }

            $hasAllocation = $installment->lines->contains(function ($line) {
                return $line->due && $line->due->allocations()->exists();
            });

            $item = [
                'id' => $installment->id,
                'sequence' => $installment->sequence,
                'due_date' => $installment->due_date->format('d.m.Y'),
                'status' => $installment->status,
                'amount' => $installment->lines->sum('amount'),
            ];

            if ($hasAllocation) {
                $blocked[] = $item;
            } elseif ($isFuture || $installment->status === AssessmentInstallment::STATUS_PENDING) {
                $cancellable[] = $item;
            }
        }

        return view('assessment-plans.cancel-preview', [
            'apartment' => $apartment,
            'plan' => $assessmentPlan,
            'cancellable' => $cancellable,
            'blocked' => $blocked,
        ]);
    }

    public function cancelFuture(Request $request, CurrentApartment $currentApartment, AssessmentPlan $assessmentPlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof RedirectResponse) {
            return $apartment;
        }
        if ($assessmentPlan->apartment_id !== $apartment->id) {
            abort(404);
        }
        $this->authorizeFeature($apartment);

        $validated = $request->validate([
            'installment_ids' => ['required', 'array', 'min:1'],
            'installment_ids.*' => ['integer'],
            'confirm' => ['accepted'],
        ]);

        $cancelled = 0;
        $skipped = 0;

        DB::transaction(function () use ($assessmentPlan, $validated, &$cancelled, &$skipped) {
            AssessmentPlan::whereKey($assessmentPlan->id)->lockForUpdate()->firstOrFail();
            $installments = AssessmentInstallment::query()
                ->where('assessment_plan_id', $assessmentPlan->id)
                ->whereIn('id', $validated['installment_ids'])
                ->with(['lines.due.allocations'])
                ->lockForUpdate()
                ->get();

            foreach ($installments as $installment) {
                if ($installment->isCancelled()) {
                    $skipped++;

                    continue;
                }

                $hasAllocation = $installment->lines->contains(
                    fn ($line) => $line->due && $line->due->allocations()->exists()
                );

                if ($hasAllocation) {
                    $skipped++;

                    continue;
                }

                foreach ($installment->lines as $line) {
                    if ($line->due && ! $line->due->trashed()) {
                        $line->due->transactions()->delete();
                        $line->due->delete();
                    }
                }

                $installment->update([
                    'status' => AssessmentInstallment::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);
                $cancelled++;
            }
        });

        return redirect()->route('assessment-plans.show', $assessmentPlan)
            ->with('status', "{$cancelled} taksit kalıcı olarak iptal edildi."
                .($skipped ? " {$skipped} taksit atlandı (tahsilat veya zaten iptal)." : ''));
    }

    private function authorizeFeature($apartment): void
    {
        if (! FeatureGate::allows($apartment, 'assessment_plans') && ! auth()->user()?->isAdmin()) {
            abort(403, 'Taksitli borç planlama özelliği bu apartman için aktif değil.');
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
