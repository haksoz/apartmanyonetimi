<?php

namespace App\Console\Commands;

use App\Http\Controllers\DuePlanController;
use App\Models\AssessmentInstallment;
use App\Models\AssessmentPlan;
use App\Models\DuePlan;
use App\Models\ExpensePlan;
use App\Support\AidatPeriodReconciliation;
use App\Support\AssessmentPlanGenerator;
use App\Support\ExpensePlanGenerator;
use App\Support\FeatureGate;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoGenerateFinancialPlans extends Command
{
    protected $signature = 'financial-plans:auto-generate {--dry-run : Gerçekten oluşturmadan göster}';

    protected $description = 'Zamanı gelen aidat, gider ve ek borçlandırma plan kayıtlarını oluşturur.';

    public function handle(
        DuePlanController $duePlanController,
        AidatPeriodReconciliation $aidatReconciliation,
        ExpensePlanGenerator $expenseGenerator,
        AssessmentPlanGenerator $assessmentGenerator,
    ): int {
        $this->generateAidat($duePlanController, $aidatReconciliation);
        $this->generateExpenses($expenseGenerator);
        $this->generateAssessments($assessmentGenerator);

        return self::SUCCESS;
    }

    private function generateAidat(DuePlanController $controller, AidatPeriodReconciliation $aidatReconciliation): void
    {
        $today = Carbon::today();
        $period = $today->format('Y-m');

        $plans = DuePlan::query()
            ->where('is_active', true)
            ->where('generation_mode', DuePlan::GENERATION_ON_SCHEDULE)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get();

        foreach ($plans as $plan) {
            if ($plan->skipsPeriod($period)) {
                continue;
            }
            $apartment = $plan->apartment;
            if (! $apartment || ! FeatureGate::allows($apartment, 'auto_dues')) {
                continue;
            }
            if ($today->day < $plan->generate_day) {
                continue;
            }

            $reconciliation = $aidatReconciliation->reconcile($plan, $period);
            $targetCount = $reconciliation['target_accounts']->count();
            $completedCount = count($reconciliation['completed_account_ids']);

            if ($targetCount > 0 && $completedCount >= $targetCount) {
                continue;
            }
            if ($completedCount > 0) {
                $this->warn("[Aidat:{$plan->name}] {$period} kısmi; yönetici onayı bekleniyor.");
                continue;
            }

            if ($this->option('dry-run')) {
                $this->info("[DRY-RUN][Aidat] {$plan->name} {$period}");
                continue;
            }

            $count = $controller->createDuesForPeriod($plan, $period, null, false);
            $this->info("[Aidat] {$plan->name} {$period}: {$count} borç");
        }
    }

    private function generateExpenses(ExpensePlanGenerator $generator): void
    {
        $today = Carbon::today();
        $period = $today->format('Y-m');

        $plans = ExpensePlan::query()
            ->where('is_active', true)
            ->where('generation_mode', ExpensePlan::GENERATION_ON_SCHEDULE)
            ->whereDate('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->get();

        foreach ($plans as $plan) {
            if (! FeatureGate::allows($plan->apartment, 'expense_plans')) {
                continue;
            }
            if ($today->day < $plan->generate_day) {
                continue;
            }
            if (! $plan->coversPeriod($period)) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->info("[DRY-RUN][Gider] {$plan->name} {$period}");
                continue;
            }

            $count = $generator->generateForPeriod($plan, $period);
            if ($count > 0) {
                $this->info("[Gider] {$plan->name} {$period}: oluşturuldu");
            }
        }
    }

    private function generateAssessments(AssessmentPlanGenerator $generator): void
    {
        $today = Carbon::today();
        $period = $today->format('Y-m');

        $plans = AssessmentPlan::query()
            ->where('is_active', true)
            ->where('generation_mode', AssessmentPlan::GENERATION_ON_SCHEDULE)
            ->get();

        foreach ($plans as $plan) {
            if (! FeatureGate::allows($plan->apartment, 'assessment_plans')) {
                continue;
            }
            if ($today->day < $plan->generate_day) {
                continue;
            }

            $installments = $plan->installments()
                ->where('status', AssessmentInstallment::STATUS_PENDING)
                ->where('period', '<=', $period)
                ->get();

            foreach ($installments as $installment) {
                if ($this->option('dry-run')) {
                    $this->info("[DRY-RUN][EkBorç] {$plan->name} taksit {$installment->sequence}");
                    continue;
                }

                $count = $generator->generateInstallment($plan, $installment);
                if ($count > 0) {
                    $this->info("[EkBorç] {$plan->name} taksit {$installment->sequence}: {$count} borç");
                }
            }
        }
    }
}
