<?php

namespace App\Support;

use App\Models\DuePlan;
use Carbon\Carbon;

class AidatPlanSummary
{
    public function __construct(private readonly AidatPeriodReconciliation $reconciliation) {}

    public function period(DuePlan $plan, string $period): array
    {
        $reconciliation = $this->reconciliation->reconcile($plan, $period);
        $targets = $reconciliation['target_accounts'];
        $count = $targets->count();
        $totalWeight = $targets->sum('weight');
        $totalCents = (int) round($plan->monthly_amount_resolved * 100);
        if ($plan->round_to_integer) {
            $totalCents = (int) ceil($totalCents / 100) * 100;
        }
        $allocated = 0;
        $snapshot = [];
        foreach ($targets as $index => $item) {
            $ratio = $plan->distribution_type === DuePlan::DISTRIBUTION_EQUAL || $totalWeight <= 0
                ? 1 / $count : $item['weight'] / $totalWeight;
            $cents = $plan->round_to_integer
                ? (int) ceil($totalCents * $ratio / 100) * 100
                : ($index === $count - 1 ? $totalCents - $allocated : (int) round($totalCents * $ratio));
            $allocated += $cents;
            $snapshot[$item['unit']->id] = $cents / 100;
        }

        return [...$reconciliation, 'snapshot' => $snapshot, 'source_amount' => array_sum($snapshot)];
    }

    public function build(DuePlan $plan, bool $includePast, ?string $currentAction = null): array
    {
        $rows = [];
        $created = 0;
        $amount = 0;
        $current = ['eligible' => false, 'action' => null, 'count' => 0, 'amount' => 0];
        $past = ['eligible' => false, 'included' => $includePast, 'months' => 0, 'count' => 0, 'amount' => 0];
        for ($month = 1; $month <= 12; $month++) {
            $date = Carbon::create($plan->plan_year, $month, 1)->startOfDay();
            $period = $date->format('Y-m');
            $calculation = $this->period($plan, $period);
            $status = ! $plan->is_active ? 'Pasif plan'
                : ($period < now()->format('Y-m') ? ($includePast ? 'Oluşturulacak' : 'Geçmiş dönem hariç') : 'Zamanı gelince');
            $accounts = [];
            foreach ($calculation['missing_accounts'] as $item) {
                $accounts[] = [
                    'account_id' => $item['account']->id,
                    'unit_id' => $item['unit']->id,
                    'label' => 'Daire '.$item['unit']->unit_no.' · '.$item['account']->name,
                    'account_type_label' => $item['account']->type_label,
                    'amount' => $calculation['snapshot'][$item['unit']->id],
                ];
            }
            if ($plan->is_active && $period < now()->format('Y-m') && $accounts !== []) {
                $past['eligible'] = true;
                $past['months']++;
                $past['count'] += count($accounts);
                $past['amount'] += array_sum(array_column($accounts, 'amount'));
            }
            if ($plan->is_active && $plan->createsOnSchedule()
                && $period === now()->format('Y-m') && now()->day >= $plan->generate_day && $accounts !== []) {
                $action = $currentAction ?? ($plan->skipsPeriod($period) ? 'skip' : null);
                $current = [
                    'eligible' => true, 'period' => $period, 'label' => $date->locale('tr')->isoFormat('MMMM YYYY'),
                    'action' => $action, 'count' => count($accounts), 'amount' => array_sum(array_column($accounts, 'amount')),
                ];
                $status = match ($action) {
                    'create' => 'Oluşturulacak',
                    'skip' => 'Bu ay atlanacak',
                    default => 'Bu ay için seçim bekleniyor',
                };
            } elseif ($plan->skipsPeriod($period) && ! ($includePast && $period < now()->format('Y-m'))) {
                $status = 'Atlanacak dönem';
            }
            if ($plan->is_active && $accounts === []) $status = 'Tamamı mevcut';
            if ($status === 'Oluşturulacak' && $accounts === []) $status = 'Tamamı mevcut';
            $newCount = $status === 'Oluşturulacak' ? count($accounts) : 0;
            $newAmount = $newCount ? array_sum(array_column($accounts, 'amount')) : 0;
            $created += $newCount;
            $amount += $newAmount;
            $rows[] = [
                'period' => $period, 'label' => $date->locale('tr')->isoFormat('MMMM YYYY'),
                'status' => $status, 'existing' => count($calculation['completed_account_ids']),
                'existing_ids' => $calculation['completed_account_ids'],
                'accounts' => $accounts, 'count' => $newCount, 'amount' => $newAmount,
            ];
        }

        return ['rows' => $rows, 'count' => $created, 'amount' => round($amount, 2), 'current' => $current, 'past' => $past, 'saved_plan' => $plan->getOriginal()];
    }
}
