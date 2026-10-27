<?php

namespace App\Support;

use App\Models\AccountTransaction;
use App\Models\Expense;
use App\Models\ExpensePlan;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ExpensePlanGenerator
{
    public function generateAll(ExpensePlan $plan, bool $includePast = false): array
    {
        $result = ['created' => 0, 'existing' => 0, 'excluded' => 0];
        if (! $plan->is_active || ! $plan->end_date) {
            return $result;
        }

        $cursor = $plan->start_date->copy()->startOfMonth();
        $end = $plan->end_date->copy()->startOfMonth();
        $currentMonth = now()->startOfMonth();
        while ($cursor <= $end) {
            $period = $cursor->format('Y-m');
            if (! $includePast && $cursor < $currentMonth) {
                $result['excluded']++;
            } else {
                $created = $this->generateForPeriod($plan, $period);
                $result[$created ? 'created' : 'existing']++;
            }
            $cursor->addMonth();
        }

        return $result;
    }

    /**
     * Planlanan ay için ödenmemiş gider + tedarikçi credit oluşturur.
     * Payment / kasa hareketi yazmaz. Gizli tedarikçi açmaz.
     *
     * @return int Oluşturulan gider sayısı (0 veya 1)
     */
    public function generateForPeriod(ExpensePlan $plan, string $periodYm): int
    {
        if (! $plan->is_active) {
            return 0;
        }

        if (! $plan->coversPeriod($periodYm)) {
            return 0;
        }

        $key = ExpensePlan::occurrenceKey($plan->id, $periodYm);

        try {
            return DB::transaction(function () use ($plan, $periodYm, $key) {
                $locked = ExpensePlan::query()->whereKey($plan->id)->lockForUpdate()->first();
                if (! $locked || ! $locked->is_active || ! $locked->coversPeriod($periodYm)
                    || in_array($periodYm, $locked->skipped_periods ?? [], true)) {
                    return 0;
                }

                $exists = Expense::query()
                    ->where('plan_occurrence_key', $key)
                    ->exists();

                if ($exists) {
                    return 0;
                }

                $periodDate = Carbon::parse($periodYm.'-01');
                $expenseDate = $periodDate->copy()->setDay(min($locked->generate_day, $periodDate->daysInMonth));
                $category = $locked->category;
                $description = $locked->description
                    ?: ($periodDate->locale('tr')->isoFormat('YYYY MMMM').' - '.($category?->name ?: $locked->name));
                $suffix = trim($locked->description_suffix ?? '');
                if ($suffix !== '') {
                    $description .= ' '.$suffix;
                }

                $expense = Expense::create([
                    'apartment_id' => $locked->apartment_id,
                    'expense_plan_id' => $locked->id,
                    'plan_occurrence_key' => $key,
                    'account_id' => $locked->account_id,
                    'category_id' => $locked->category_id,
                    'category' => $category?->name,
                    'description' => $description,
                    'amount' => $locked->amount,
                    'paid_amount' => 0,
                    'remaining_amount' => $locked->amount,
                    'expense_date' => $expenseDate->toDateString(),
                    'due_date' => $locked->due_days !== null
                        ? $expenseDate->copy()->addDays($locked->due_days)->toDateString()
                        : null,
                    'period_month' => $periodDate->toDateString(),
                    'is_paid' => false,
                ]);

                AccountTransaction::create([
                    'apartment_id' => $locked->apartment_id,
                    'account_id' => $locked->account_id,
                    'transactionable_type' => Expense::class,
                    'transactionable_id' => $expense->id,
                    'type' => 'credit',
                    'description' => $description,
                    'amount' => $locked->amount,
                    'transaction_date' => $expenseDate->toDateString(),
                ]);

                return 1;
            });
        } catch (QueryException $e) {
            // UNIQUE race: başka süreç aynı dönemi oluşturdu
            if (str_contains(strtolower($e->getMessage()), 'unique')
                || str_contains(strtolower($e->getMessage()), 'duplicate')) {
                return 0;
            }
            throw $e;
        }
    }
}
