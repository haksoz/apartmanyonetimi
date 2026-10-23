<?php

namespace App\Http\Controllers;

use App\Models\AssessmentPlan;
use App\Models\DuePlan;
use App\Models\ExpensePlan;
use App\Support\CurrentApartment;
use App\Support\FeatureGate;
use Illuminate\Http\Request;

class FinancialPlanController extends Controller
{
    public function index(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        $canAidat = FeatureGate::allows($apartment, 'auto_dues');
        $canExpense = FeatureGate::allows($apartment, 'expense_plans');
        $canAssessment = FeatureGate::allows($apartment, 'assessment_plans');

        if (! $canAidat && ! $canExpense && ! $canAssessment && ! auth()->user()?->isAdmin()) {
            abort(403, 'Finansal planlama özelliği bu apartman için aktif değil.');
        }

        $rows = collect();

        if ($canAidat || auth()->user()?->isAdmin()) {
            DuePlan::query()
                ->where('apartment_id', $apartment->id)
                ->orderByDesc('plan_year')
                ->get()
                ->each(function (DuePlan $plan) use ($rows) {
                    $rows->push([
                        'type' => 'aidat',
                        'type_label' => 'Aidat Planı',
                        'generation_mode' => $plan->generation_mode ?: 'on_schedule',
                        'name' => $plan->name.' ('.$plan->plan_year.')',
                        'period_label' => (string) $plan->plan_year,
                        'is_active' => (bool) $plan->is_active,
                        'is_expired' => $plan->is_active && $plan->end_date?->lt(today()),
                        'amount' => $plan->monthly_amount,
                        'url' => route('due-plans.index', $plan->pageParameters()),
                        'sort' => sprintf('%04d-aidat-%d', $plan->plan_year, $plan->id),
                    ]);
                });
        }

        if ($canExpense || auth()->user()?->isAdmin()) {
            ExpensePlan::query()
                ->where('apartment_id', $apartment->id)
                ->orderByDesc('id')
                ->get()
                ->each(function (ExpensePlan $plan) use ($rows) {
                    $rows->push([
                        'type' => 'expense',
                        'type_label' => 'Gider Planı',
                        'generation_mode' => $plan->generation_mode ?: 'on_schedule',
                        'name' => $plan->name,
                        'period_label' => $plan->start_date?->format('m.Y')
                            .($plan->end_date ? ' – '.$plan->end_date->format('m.Y') : ' – devam'),
                        'is_active' => (bool) $plan->is_active,
                        'amount' => $plan->amount,
                        'url' => route('expense-plans.show', $plan),
                        'sort' => sprintf('%s-expense-%d', $plan->start_date?->format('Y-m') ?? '0000', $plan->id),
                    ]);
                });
        }

        if ($canAssessment || auth()->user()?->isAdmin()) {
            AssessmentPlan::query()
                ->where('apartment_id', $apartment->id)
                ->orderByDesc('id')
                ->get()
                ->each(function (AssessmentPlan $plan) use ($rows) {
                    $rows->push([
                        'type' => 'assessment',
                        'type_label' => 'Taksitli Borç',
                        'generation_mode' => $plan->generation_mode ?: 'on_schedule',
                        'name' => $plan->name,
                        'period_label' => $plan->installment_count.' taksit',
                        'is_active' => (bool) $plan->is_active,
                        'amount' => $plan->total_amount,
                        'url' => route('assessment-plans.show', $plan),
                        'sort' => sprintf('%s-assessment-%d', $plan->created_at?->format('Y-m') ?? '0000', $plan->id),
                    ]);
                });
        }

        $plans = $rows->sortByDesc('sort')->values();

        return view('financial-plans.index', compact(
            'apartment',
            'plans',
            'canAidat',
            'canExpense',
            'canAssessment',
        ));
    }

    public function create(CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        $canAidat = FeatureGate::allows($apartment, 'auto_dues');
        $canExpense = FeatureGate::allows($apartment, 'expense_plans');
        $canAssessment = FeatureGate::allows($apartment, 'assessment_plans');

        if (! $canAidat && ! $canExpense && ! $canAssessment && ! auth()->user()?->isAdmin()) {
            abort(403);
        }

        $hasCurrentYearAidatPlan = DuePlan::query()
            ->where('apartment_id', $apartment->id)
            ->where('plan_year', now()->year)
            ->where('is_active', true)
            ->exists();

        return view('financial-plans.create', compact('apartment', 'canAidat', 'canExpense', 'canAssessment', 'hasCurrentYearAidatPlan'));
    }

    public function selectType(Request $request, CurrentApartment $currentApartment)
    {
        $apartment = $this->resolveApartment($currentApartment);
        if ($apartment instanceof \Illuminate\Http\RedirectResponse) {
            return $apartment;
        }

        $validated = $request->validate([
            'type' => ['required', 'in:aidat,expense,assessment'],
        ]);

        if ($validated['type'] === 'aidat' && DuePlan::query()
            ->where('apartment_id', $apartment->id)
            ->where('plan_year', now()->year)
            ->where('is_active', true)
            ->exists()) {
            return redirect()->route('financial-plans.create')
                ->withErrors(['type' => 'Bu yıl için aktif bir aidat planı zaten var.']);
        }

        return match ($validated['type']) {
            'aidat' => redirect()->route('due-plans.index', ['year' => now()->year, 'new' => 1]),
            'expense' => redirect()->route('expense-plans.create'),
            'assessment' => redirect()->route('assessment-plans.create'),
        };
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
