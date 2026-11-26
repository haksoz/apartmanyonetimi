<?php

namespace App\Support;

use App\Models\Apartment;
use App\Models\AssessmentInstallment;
use App\Models\AssessmentPlan;
use App\Models\DuePlan;
use App\Models\PlanReminderDismissal;
use App\Models\User;

class MissingPlanReminders
{
    public function forApartment(Apartment $apartment, User $user): array
    {
        $period = now()->format('Y-m');
        $dismissed = PlanReminderDismissal::where('user_id', $user->id)->where('apartment_id', $apartment->id)
            ->where('period', $period)->get()->mapWithKeys(fn ($row) => [$row->plan_type.':'.$row->plan_id => true]);
        $notices = [];
        $plans = DuePlan::where('apartment_id', $apartment->id)->where('is_active', true)
            ->whereDate('start_date', '<=', now())->whereDate('end_date', '>=', now())->get();
        foreach ($plans as $plan) {
            if ($dismissed->has('aidat:'.$plan->id) || $plan->skipsPeriod($period) || now()->day < $plan->generate_day) {
                continue;
            }
            $summary = app(AidatPeriodReconciliation::class)->reconcile($plan, $period);
            $completed = count($summary['completed_account_ids']);
            $expected = $summary['target_accounts']->count();
            if ($completed > 0 && $completed < $expected) {
                $notices[] = ['type' => 'aidat', 'plan_id' => $plan->id, 'name' => $plan->name, 'period' => $period,
                    'expected' => $expected, 'existing' => $completed, 'missing' => $expected - $completed,
                    'url' => route('due-plans.period-detail', ['duePlan' => $plan->id, 'period' => $period])];
            }
        }
        if (FeatureGate::allows($apartment, 'assessment_plans', $user)) {
            $plans = AssessmentPlan::where('apartment_id', $apartment->id)->where('is_active', true)
                ->with(['installments' => fn ($q) => $q->where('period', $period)->where('status', AssessmentInstallment::STATUS_GENERATED)->with('lines.due')])->get();
            foreach ($plans as $plan) {
                if ($dismissed->has('assessment:'.$plan->id) || in_array($period, $plan->skipped_periods ?? [], true)
                    || ($plan->createsOnSchedule() && now()->day < $plan->generate_day)) {
                    continue;
                }
                $lines = $plan->installments->flatMap(fn ($installment) => $installment->lines);
                $expected = $lines->count();
                $completed = $lines->filter(fn ($line) => $line->due !== null)->count();
                if ($expected > 0 && $completed < $expected) {
                    $notices[] = ['type' => 'assessment', 'plan_id' => $plan->id, 'name' => $plan->name, 'period' => $period,
                        'expected' => $expected, 'existing' => $completed, 'missing' => $expected - $completed,
                        'url' => route('assessment-plans.show', $plan)];
                }
            }
        }

        return $notices;
    }
}
