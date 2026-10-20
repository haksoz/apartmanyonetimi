<?php

namespace App\Support;

use App\Enums\DueType;
use App\Models\AccountTransaction;
use App\Models\AssessmentInstallment;
use App\Models\AssessmentInstallmentLine;
use App\Models\AssessmentPlan;
use App\Models\AssessmentUnitShare;
use App\Models\Due;
use App\Models\DueBatch;
use App\Models\DuePlan;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AssessmentPlanGenerator
{
    public function __construct(private readonly AidatPeriodReconciliation $aidatReconciliation)
    {
    }

    /**
     * Plan kaydı sonrası daire payları ve taksit satırlarını kalıcı yazar.
     *
     * @param  array<int, array{due_date: string}>  $installmentDates
     */
    public function buildDistribution(AssessmentPlan $plan, array $installmentDates): void
    {
        $units = Unit::query()
            ->where('apartment_id', $plan->apartment_id)
            ->orderBy('unit_no')
            ->get();

        $eligible = [];
        foreach ($units as $unit) {
            $weight = match ($plan->distribution_type) {
                DuePlan::DISTRIBUTION_SQUARE_METERS => (float) ($unit->square_meters ?? 0),
                DuePlan::DISTRIBUTION_SHARE_COEFFICIENT => (float) ($unit->share_coefficient ?? 0),
                default => 1.0,
            };

            if ($weight <= 0 && $plan->distribution_type !== DuePlan::DISTRIBUTION_EQUAL) {
                continue;
            }

            $eligible[] = [
                'unit' => $unit,
                'weight' => $plan->distribution_type === DuePlan::DISTRIBUTION_EQUAL ? 1.0 : $weight,
            ];
        }

        if ($eligible === []) {
            return;
        }

        $totalAmount = (float) $plan->total_amount;
        $totalWeight = array_sum(array_column($eligible, 'weight'));
        $count = count($eligible);
        $allocated = 0.0;
        $shares = [];

        foreach ($eligible as $index => $item) {
            $isLast = $index === $count - 1;
            $share = $isLast
                ? round($totalAmount - $allocated, 2)
                : round($totalAmount * $item['weight'] / $totalWeight, 2);
            $allocated += $share;
            $shares[] = [
                'unit' => $item['unit'],
                'share_amount' => $share,
                'weight' => $item['weight'],
            ];
        }

        $installmentCount = count($installmentDates);

        DB::transaction(function () use ($plan, $shares, $installmentDates, $installmentCount) {
            foreach ($shares as $share) {
                AssessmentUnitShare::create([
                    'assessment_plan_id' => $plan->id,
                    'unit_id' => $share['unit']->id,
                    'share_amount' => $share['share_amount'],
                    'weight' => $share['weight'],
                    'square_meters' => $share['unit']->square_meters,
                    'share_coefficient' => $share['unit']->share_coefficient,
                ]);
            }

            foreach ($installmentDates as $i => $row) {
                $dueDate = Carbon::parse($row['due_date']);
                AssessmentInstallment::create([
                    'assessment_plan_id' => $plan->id,
                    'sequence' => $i + 1,
                    'due_date' => $dueDate->toDateString(),
                    'period' => $dueDate->format('Y-m'),
                    'status' => AssessmentInstallment::STATUS_PENDING,
                ]);
            }

            $installments = $plan->installments()->orderBy('sequence')->get();
            foreach ($shares as $share) {
                $unitTotal = (float) $share['share_amount'];
                $allocatedUnit = 0.0;
                foreach ($installments as $idx => $installment) {
                    $isLast = $idx === $installmentCount - 1;
                    $amount = $isLast
                        ? round($unitTotal - $allocatedUnit, 2)
                        : round($unitTotal / $installmentCount, 2);
                    $allocatedUnit += $amount;
                    AssessmentInstallmentLine::create([
                        'assessment_plan_id' => $plan->id,
                        'assessment_installment_id' => $installment->id,
                        'unit_id' => $share['unit']->id,
                        'amount' => $amount,
                    ]);
                }
            }
        });
    }

    /**
     * @return int Oluşturulan Due sayısı
     */
    public function generateInstallment(AssessmentPlan $plan, AssessmentInstallment $installment): int
    {
        if (! $plan->is_active) {
            return 0;
        }

        if ($installment->isCancelled() || $installment->status === AssessmentInstallment::STATUS_GENERATED) {
            return 0;
        }

        $installment->loadMissing('lines.unit');
        if ($installment->lines->isEmpty()) {
            return 0;
        }

        $periodDate = Carbon::parse($installment->period.'-01');
        $created = 0;

        DB::transaction(function () use ($plan, $installment, $periodDate, &$created) {
            $lockedPlan = AssessmentPlan::query()->whereKey($plan->id)->lockForUpdate()->first();
            $lockedInst = AssessmentInstallment::query()->whereKey($installment->id)->lockForUpdate()->first();

            if (! $lockedPlan?->is_active || ! $lockedInst || $lockedInst->isCancelled()
                || $lockedInst->status === AssessmentInstallment::STATUS_GENERATED) {
                return;
            }

            $batch = DueBatch::create([
                'apartment_id' => $plan->apartment_id,
                'assessment_plan_id' => $plan->id,
                'assessment_installment_id' => $installment->id,
                'due_type' => DueType::MalikPayi,
                'category_id' => $plan->category_id,
                'source_type' => DueBatch::SOURCE_MANUAL,
                'distribution_type' => $plan->distribution_type,
                'target_audience' => $plan->target_audience,
                'period' => $installment->period,
                'source_amount' => $installment->lines->sum('amount'),
                'description' => $plan->name.' — Taksit '.$installment->sequence,
                'created_by' => auth()->id(),
            ]);

            foreach ($installment->lines as $line) {
                if ($line->due_id) {
                    continue;
                }

                $unit = $line->unit;
                $account = $this->aidatReconciliation->accountForPeriod(
                    $unit,
                    $periodDate,
                    $plan->target_audience,
                );

                if (! $account) {
                    continue;
                }

                $due = Due::create([
                    'apartment_id' => $plan->apartment_id,
                    'due_batch_id' => $batch->id,
                    'unit_id' => $unit->id,
                    'account_id' => $account->id,
                    'due_type' => DueType::MalikPayi,
                    'category_id' => $plan->category_id,
                    'period' => $installment->period,
                    'amount' => $line->amount,
                    'remaining_amount' => $line->amount,
                    'due_date' => $installment->due_date,
                    'status' => 'unpaid',
                    'description' => $plan->name.' — Taksit '.$installment->sequence,
                    'created_at_manual' => $periodDate->toDateString(),
                ]);

                AccountTransaction::create([
                    'apartment_id' => $plan->apartment_id,
                    'account_id' => $account->id,
                    'transactionable_type' => Due::class,
                    'transactionable_id' => $due->id,
                    'type' => 'debit',
                    'description' => $due->description,
                    'amount' => $line->amount,
                    'transaction_date' => $periodDate->toDateString(),
                ]);

                $line->update(['due_id' => $due->id]);
                $created++;
            }

            $lockedInst->update([
                'status' => AssessmentInstallment::STATUS_GENERATED,
                'generated_at' => now(),
                'due_batch_id' => $batch->id,
            ]);
        });

        return $created;
    }

    /**
     * @return int Toplam Due sayısı
     */
    public function generateEligibleInstallments(AssessmentPlan $plan, bool $includePast): int
    {
        if (! $plan->is_active) {
            return 0;
        }

        $today = Carbon::today();
        $total = 0;

        foreach ($plan->installments()->where('status', AssessmentInstallment::STATUS_PENDING)->get() as $installment) {
            $isPast = $installment->due_date->lt($today->copy()->startOfDay());
            if ($isPast && ! $includePast) {
                continue;
            }

            if ($plan->createsOnSchedule() && ! $includePast) {
                // Zamanı geldikçe: yalnız generate_day gelmiş ve dönem ayı bugünün ayı veya geçmiş (onaylı)
                if ($installment->period > $today->format('Y-m')) {
                    continue;
                }
                if ($installment->period === $today->format('Y-m') && $today->day < $plan->generate_day) {
                    continue;
                }
            }

            $total += $this->generateInstallment($plan, $installment);
        }

        return $total;
    }
}
