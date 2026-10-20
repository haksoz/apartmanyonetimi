<?php

namespace App\Http\Controllers;

use App\Enums\DueType;
use App\Models\AssessmentInstallment;
use App\Models\AssessmentPlan;
use App\Models\Category;
use App\Models\DuePlan;
use App\Support\AssessmentPlanGenerator;
use App\Support\CurrentApartment;
use App\Support\FeatureGate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssessmentPlanController extends Controller
{
    public function __construct(private readonly AssessmentPlanGenerator $generator)
    {
    }

    public function create(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        $this->authorizeFeature($apartment);

        $categories = Category::query()
            ->where('apartment_id', $apartment->id)
            ->where('type', Category::TYPE_INCOME)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('assessment-plans.create', compact('apartment', 'categories'));
    }

    public function store(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        $this->authorizeFeature($apartment);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'distribution_type' => ['required', Rule::in([
                DuePlan::DISTRIBUTION_EQUAL,
                DuePlan::DISTRIBUTION_SQUARE_METERS,
                DuePlan::DISTRIBUTION_SHARE_COEFFICIENT,
            ])],
            'target_audience' => ['required', Rule::in(['tenant_priority', 'owner_only'])],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('apartment_id', $apartment->id),
            ],
            'installment_count' => ['required', 'integer', 'min:1', 'max:60'],
            'first_due_date' => ['required', 'date'],
            'due_interval_months' => ['required', 'integer', 'min:1', 'max:12'],
            'generation_mode' => ['required', Rule::in([
                AssessmentPlan::GENERATION_CREATE_ALL_NOW,
                AssessmentPlan::GENERATION_ON_SCHEDULE,
            ])],
            'generate_day' => ['required', 'integer', 'min:1', 'max:28'],
            'include_past_installments' => ['nullable', 'boolean'],
            'is_active' => ['boolean'],
        ]);

        $count = (int) $validated['installment_count'];
        $interval = (int) $validated['due_interval_months'];
        $first = Carbon::parse($validated['first_due_date']);
        $dates = [];
        for ($i = 0; $i < $count; $i++) {
            $dates[] = ['due_date' => $first->copy()->addMonthsNoOverflow($i * $interval)->toDateString()];
        }

        $plan = DB::transaction(function () use ($apartment, $validated, $request, $dates) {
            $plan = AssessmentPlan::create([
                'apartment_id' => $apartment->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'total_amount' => $validated['total_amount'],
                'distribution_type' => $validated['distribution_type'],
                'target_audience' => $validated['target_audience'],
                'due_type' => DueType::MalikPayi,
                'category_id' => $validated['category_id'] ?? null,
                'installment_count' => $validated['installment_count'],
                'generation_mode' => $validated['generation_mode'],
                'generate_day' => $validated['generate_day'],
                'is_active' => $request->boolean('is_active', true),
            ]);

            $this->generator->buildDistribution($plan, $dates);

            return $plan->fresh(['installments']);
        });

        if ($plan->is_active && $plan->generation_mode === AssessmentPlan::GENERATION_CREATE_ALL_NOW) {
            $past = $plan->installments
                ->filter(fn ($i) => $i->due_date->lt(now()->startOfDay()))
                ->values();

            if ($past->isNotEmpty() && ! $request->has('include_past_installments')) {
                return redirect()->route('assessment-plans.show', $plan)
                    ->with('prompt_past_installments', true)
                    ->with('status', 'Ek borçlandırma planı kaydedildi. Geçmiş vadeler için onay bekleniyor.');
            }

            $created = $this->generator->generateEligibleInstallments(
                $plan,
                $request->boolean('include_past_installments', true),
            );

            return redirect()->route('assessment-plans.show', $plan)
                ->with('status', "Plan kaydedildi. {$created} borç kaydı oluşturuldu.");
        }

        return redirect()->route('assessment-plans.show', $plan)
            ->with('status', 'Ek borçlandırma planı kaydedildi.');
    }

    public function show(CurrentApartment $currentApartment, AssessmentPlan $assessmentPlan, Request $request)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
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

        $promptPast = (bool) session('prompt_past_installments');
        $pastInstallments = $assessmentPlan->installments
            ->filter(fn ($i) => $i->status === AssessmentInstallment::STATUS_PENDING
                && $i->due_date->lt(now()->startOfDay()))
            ->values();

        return view('assessment-plans.show', [
            'apartment' => $apartment,
            'plan' => $assessmentPlan,
            'promptPast' => $promptPast,
            'pastInstallments' => $pastInstallments,
        ]);
    }

    public function update(Request $request, CurrentApartment $currentApartment, AssessmentPlan $assessmentPlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        if ($assessmentPlan->apartment_id !== $apartment->id) {
            abort(404);
        }
        $this->authorizeFeature($apartment);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'generate_day' => ['required', 'integer', 'min:1', 'max:28'],
            'is_active' => ['required', 'boolean'],
        ]);
        $assessmentPlan->update($validated);

        return redirect()->route('assessment-plans.show', $assessmentPlan)
            ->with('status', 'Plan güncellendi. Mevcut taksitler ve borçlar korundu.');
    }

    public function confirmPast(Request $request, CurrentApartment $currentApartment, AssessmentPlan $assessmentPlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }
        if ($assessmentPlan->apartment_id !== $apartment->id) {
            abort(404);
        }
        $this->authorizeFeature($apartment);

        if (! $assessmentPlan->is_active) {
            return redirect()->route('assessment-plans.show', $assessmentPlan)
                ->with('error', 'Pasif plan üzerinden borç oluşturulamaz.');
        }

        $created = $this->generator->generateEligibleInstallments(
            $assessmentPlan,
            $request->boolean('include_past_installments'),
        );

        return redirect()->route('assessment-plans.show', $assessmentPlan)
            ->with('status', "{$created} borç kaydı oluşturuldu.");
    }

    public function deactivate(CurrentApartment $currentApartment, AssessmentPlan $assessmentPlan)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
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
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
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
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
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
            abort(403, 'Ek borçlandırma planlama özelliği bu apartman için aktif değil.');
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
