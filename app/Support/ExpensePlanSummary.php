<?php

namespace App\Support;

use App\Models\Expense;
use App\Models\ExpensePlan;

class ExpensePlanSummary
{
    public function build(ExpensePlan $plan, bool $includePast = false, ?string $currentAction = null): array
    {
        $existing = $plan->exists ? Expense::where('expense_plan_id', $plan->id)
            ->whereNotNull('plan_occurrence_key')->orderBy('id')->get() : collect();
        $bulk = $plan->generation_mode === ExpensePlan::GENERATION_CREATE_ALL_NOW;
        $cursor = $plan->start_date->copy()->startOfMonth();
        $end = $plan->end_date?->copy()->startOfMonth()
            ?? $cursor->copy()->max(now()->startOfMonth())->addMonths(2);
        $rows = [];
        $currentEligible = false;
        $pastCount = 0;
        $count = 0;
        while ($cursor <= $end) {
            $period = $cursor->format('Y-m');
            $expense = $existing->firstWhere('plan_occurrence_key', ExpensePlan::occurrenceKey($plan->id ?? 0, $period));
            $date = $cursor->copy()->day(min($plan->generate_day, $cursor->daysInMonth));
            $past = $period < now()->format('Y-m');
            $current = $period === now()->format('Y-m');
            $skipped = in_array($period, $plan->skipped_periods ?? [], true) && ($bulk || ! $current);
            $eligible = $plan->is_active && ! $expense && ! $skipped;
            if ($eligible && $past) {
                $pastCount++;
            }
            if ($eligible && ! $bulk && $current && $date->lte(today())) {
                $currentEligible = true;
            }
            $create = $eligible && ($past ? $includePast : ($bulk || ($current && $date->lte(today()) && $currentAction === 'create')));
            $status = $expense ? 'Mevcut' : (! $plan->is_active ? 'Pasif' : ($skipped ? 'Atlandı' : ($create ? 'Oluşturulacak' : ($past ? 'Dahil edilmedi' : ($current && $currentEligible ? ($currentAction === 'skip' ? 'Atlanacak' : 'Seçim bekliyor') : 'Zamanı bekleniyor')))));
            $description = $plan->description ?: $cursor->copy()->locale('tr')->isoFormat('YYYY MMMM').' - '.($plan->category?->name ?: $plan->name);
            $description = trim($description.' '.trim($plan->description_suffix ?? ''));
            $rows[] = [
                'period' => $period, 'label' => $cursor->copy()->locale('tr')->isoFormat('MMMM YYYY'),
                'date' => $expense?->expense_date?->format('d.m.Y') ?? $date->format('d.m.Y'),
                'due' => $expense ? $expense->due_date?->format('d.m.Y') : ($plan->due_days !== null ? $date->copy()->addDays($plan->due_days)->format('d.m.Y') : null),
                'description' => $expense?->description ?? $description,
                'amount' => $expense ? $expense->amount : $plan->amount,
                'expense_id' => $expense?->id, 'status' => $status, 'create' => $create,
            ];
            $count += (int) $create;
            $cursor->addMonth();
        }

        return [
            'rows' => $rows, 'count' => $count, 'amount' => $count * (int) round((float) $plan->amount * 100) / 100,
            'existing' => count(array_filter($rows, fn ($row) => $row['expense_id'] !== null)),
            'past_count' => $pastCount, 'include_past' => $includePast,
            'current_eligible' => $currentEligible, 'current_action' => $currentAction,
            'snapshot' => $existing->map(fn ($expense) => $expense->getAttributes())->all(),
            'plan_snapshot' => $plan->exists ? ExpensePlan::findOrFail($plan->id)->getAttributes() : null,
        ];
    }
}
