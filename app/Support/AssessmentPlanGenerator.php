<?php

namespace App\Support;

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
    public function __construct(private readonly AidatPeriodReconciliation $aidatReconciliation) {}

    /**
     * Kayıt yazmadan daire paylarını hesaplar; özet ve kayıt aynı hesabı kullanır.
     */
    public function distributionShares(AssessmentPlan $plan): array
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
            return [];
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

        return $shares;
    }

    public function groupedPreview(AssessmentPlan $plan): array
    {
        $shares = $this->distributionShares($plan);
        $groups = [];
        foreach ($shares as $share) {
            $key = (string) $share['weight'];
            $amounts = $this->installmentAmounts((float) $share['share_amount'], $plan->installment_count);
            $first = $amounts[0];
            $last = $amounts[count($amounts) - 1];
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'weight' => $share['weight'], 'count' => 0,
                    'share_min' => $share['share_amount'], 'share_max' => $share['share_amount'],
                    'installment_min' => $first, 'installment_max' => $first,
                    'last_min' => $last, 'last_max' => $last,
                ];
            }
            $group = &$groups[$key];
            $group['count']++;
            $group['share_min'] = min($group['share_min'], $share['share_amount']);
            $group['share_max'] = max($group['share_max'], $share['share_amount']);
            $group['installment_min'] = min($group['installment_min'], $first);
            $group['installment_max'] = max($group['installment_max'], $first);
            $group['last_min'] = min($group['last_min'], $last);
            $group['last_max'] = max($group['last_max'], $last);
            unset($group);
        }
        usort($groups, fn ($left, $right) => $left['weight'] <=> $right['weight']);

        return [
            'total' => (float) $plan->total_amount,
            'installment_count' => $plan->installment_count,
            'unit_count' => count($shares),
            'excluded_count' => Unit::where('apartment_id', $plan->apartment_id)->count() - count($shares),
            'groups' => array_values($groups),
        ];
    }

    public function installmentAmounts(float $total, int $count): array
    {
        $allocated = 0.0;
        $amounts = [];
        for ($i = 0; $i < $count; $i++) {
            $amount = $i === $count - 1 ? round($total - $allocated, 2) : round($total / $count, 2);
            $allocated += $amount;
            $amounts[] = $amount;
        }

        return $amounts;
    }

    /** @param array<int, array{due_date: string}> $installmentDates */
    public function buildDistribution(AssessmentPlan $plan, array $installmentDates): void
    {
        $shares = $this->distributionShares($plan);
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
                $amounts = $this->installmentAmounts((float) $share['share_amount'], $installmentCount);
                foreach ($installments as $idx => $installment) {
                    $amount = $amounts[$idx];
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

        if ($installment->isCancelled()) {
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

            if (! $lockedPlan?->is_active || ! $lockedInst || $lockedInst->assessment_plan_id !== $lockedPlan->id
                || in_array($lockedInst->period, $lockedPlan->skipped_periods ?? [], true) || $lockedInst->isCancelled()) {
                return;
            }

            $installment->load(['lines.unit', 'lines.due']);
            if (! $installment->has_missing_dues) {
                return;
            }
            $plan = $lockedPlan;
            $batch = DueBatch::create([
                'apartment_id' => $plan->apartment_id,
                'assessment_plan_id' => $plan->id,
                'assessment_installment_id' => $installment->id,
                'due_type' => $plan->due_type,
                'category_id' => $plan->category_id,
                'source_type' => DueBatch::SOURCE_MANUAL,
                'distribution_type' => $plan->distribution_type,
                'target_audience' => $plan->due_type->targetAudience(),
                'period' => $installment->period,
                'source_amount' => $installment->lines->sum('amount'),
                'description' => $plan->installmentDescription($installment->sequence),
                'created_by' => auth()->id(),
            ]);

            foreach ($installment->lines as $line) {
                if ($line->due !== null) {
                    continue;
                }

                $unit = $line->unit;
                $account = $this->aidatReconciliation->accountForPeriod(
                    $unit,
                    $periodDate,
                    $plan->due_type,
                );

                if (! $account) {
                    continue;
                }

                $due = Due::create([
                    'apartment_id' => $plan->apartment_id,
                    'due_batch_id' => $batch->id,
                    'target_audience' => $plan->due_type->targetAudience(),
                    'unit_id' => $unit->id,
                    'account_id' => $account->id,
                    'due_type' => $plan->due_type,
                    'category_id' => $plan->category_id,
                    'period' => $installment->period,
                    'amount' => $line->amount,
                    'remaining_amount' => $line->amount,
                    'due_date' => $installment->due_date,
                    'status' => 'unpaid',
                    'description' => $plan->installmentDescription($installment->sequence),
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

            if ($plan->createsOnSchedule()) {
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
