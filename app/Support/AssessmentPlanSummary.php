<?php

namespace App\Support;

use App\Models\AssessmentInstallment;
use App\Models\AssessmentPlan;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class AssessmentPlanSummary
{
    public function __construct(private readonly AssessmentPlanGenerator $generator, private readonly AidatPeriodReconciliation $accounts) {}

    public function build(AssessmentPlan $plan, array $input, bool $includePast = false, ?string $currentAction = null): array
    {
        $snapshot = [];
        $specs = [];
        if ($plan->exists) {
            $installments = $plan->installments()->with(['lines.unit', 'lines.due.account'])->get();
            $shares = $plan->unitShares()->with('unit')->get()->map(fn ($share) => [
                'unit' => $share->unit, 'share_amount' => $share->share_amount,
            ])->all();
            foreach ($installments as $installment) {
                $snapshot[] = [$installment->getAttributes(), $installment->lines->map(fn ($line) => [$line->getAttributes(), $line->due?->getAttributes()])->all()];
                $specs[] = [
                    'id' => $installment->id, 'sequence' => $installment->sequence,
                    'due_date' => $installment->due_date, 'status' => $installment->status,
                    'description' => $installment->lines->first(fn ($line) => $line->due !== null)?->due?->description,
                    'lines' => $installment->lines->map(fn ($line) => [
                        'unit' => $line->unit, 'amount' => (float) $line->amount, 'due_id' => $line->due_id, 'due_exists' => $line->due !== null,
                        'existing_account' => $line->due?->account,
                    ])->all(),
                ];
            }
        } else {
            $shares = $this->generator->distributionShares($plan);
            if ($shares === []) {
                throw ValidationException::withMessages(['distribution_type' => 'Dağıtım için uygun daire bulunamadı. Daire ve dağıtım bilgilerini kontrol edin.']);
            }
            $amounts = [];
            foreach ($shares as $share) {
                $amounts[$share['unit']->id] = $this->generator->installmentAmounts((float) $share['share_amount'], (int) $plan->installment_count);
            }
            $first = Carbon::createFromFormat('!Y-m', $input['start_period'])->day((int) $input['due_day']);
            for ($i = 0; $i < $plan->installment_count; $i++) {
                $specs[] = [
                    'id' => null, 'sequence' => $i + 1,
                    'due_date' => $first->copy()->addMonthsNoOverflow($i * (int) $input['due_interval_months']),
                    'status' => AssessmentInstallment::STATUS_PENDING,
                    'lines' => array_map(fn ($share) => ['unit' => $share['unit'], 'amount' => $amounts[$share['unit']->id][$i], 'due_id' => null], $shares),
                ];
            }
        }
        $rows = [];
        $currentEligible = false;
        $pastCount = 0;
        $count = 0;
        $totalCents = 0;
        $existingCount = 0;
        foreach ($specs as $spec) {
            $date = $spec['due_date'];
            $period = $date->format('Y-m');
            $past = $period < now()->format('Y-m');
            $current = $period === now()->format('Y-m');
            $skipped = in_array($period, $plan->skipped_periods ?? [], true) && (! $current || ! $plan->createsOnSchedule());
            $accounts = [];
            $existing = 0;
            $missingAccounts = 0;
            foreach ($spec['lines'] as $line) {
                $liveDue = $line['due_id'] !== null && ($line['due_exists'] ?? false);
                $existing += (int) $liveDue;
                $account = $liveDue ? $line['existing_account'] : ($line['unit'] ? $this->accounts->accountForPeriod($line['unit'], $date->copy()->startOfMonth(), $plan->due_type) : null);
                if (! $liveDue && ! $account) {
                    $missingAccounts++;
                }
                $accounts[] = [
                    'unit_id' => $line['unit']?->id, 'unit' => $line['unit']?->unit_no ?? '—',
                    'account_id' => $account?->id, 'account' => $account?->name,
                    'account_type_label' => $account?->type_label,
                    'amount' => $line['amount'], 'existing' => $liveDue, 'linked' => $liveDue,
                ];
            }
            $candidates = array_filter($accounts, fn ($account) => ! $account['linked'] && $account['account_id'] !== null);
            $eligible = $plan->is_active && $spec['status'] !== AssessmentInstallment::STATUS_CANCELLED && ! $skipped && $candidates !== [];
            $currentDue = $current && today()->day >= $plan->generate_day && $plan->createsOnSchedule();
            $currentEligible = $currentEligible || ($eligible && $currentDue);
            $pastCount += (int) ($eligible && $past);
            $selected = $eligible && ($past ? $includePast : (! $plan->createsOnSchedule() || ($currentDue && $currentAction === 'create')));
            $rowCount = $selected ? count($candidates) : 0;
            $cents = $selected ? array_sum(array_map(fn ($account) => (int) round($account['amount'] * 100), $candidates)) : 0;
            $status = match (true) {
                $spec['status'] === AssessmentInstallment::STATUS_CANCELLED => 'İptal',
                $spec['status'] === AssessmentInstallment::STATUS_GENERATED && $existing === count($spec['lines']) => 'Mevcut',
                ! $plan->is_active => 'Pasif',
                $skipped => 'Atlandı',
                $selected => 'Oluşturulacak',
                $eligible && $past => 'Dahil edilmedi',
                $eligible && $currentDue => $currentAction === 'skip' ? 'Atlanacak' : 'Seçim bekliyor',
                $candidates === [] && $missingAccounts > 0 => 'Borçlanacak hesap yok',
                default => 'Zamanı bekleniyor',
            };
            $rows[] = [
                'installment_id' => $spec['id'], 'sequence' => $spec['sequence'], 'period' => $period,
                'date' => $date->format('d.m.Y'), 'due_date' => $date->toDateString(),
                'description' => $spec['description'] ?? $plan->installmentDescription($spec['sequence']),
                'scheduled_date' => $date->copy()->startOfMonth()->day($plan->generate_day)->format('d.m.Y'),
                'creation_date' => $date->copy()->startOfMonth()->day($plan->generate_day)->format('d.m.Y'),
                'status' => $status, 'existing' => $existing, 'count' => $rowCount, 'amount' => $cents / 100,
                'total' => array_sum(array_column($accounts, 'amount')), 'accounts' => $accounts,
                'selected' => $selected, 'missing_accounts' => $missingAccounts,
            ];
            $count += $rowCount;
            $totalCents += $cents;
            $existingCount += $existing;
        }

        return [
            'rows' => $rows, 'count' => $count, 'amount' => $totalCents / 100,
            'existing' => $existingCount, 'past_count' => $pastCount,
            'include_past' => $includePast, 'current_eligible' => $currentEligible, 'current_action' => $currentAction,
            'shares' => array_map(fn ($share) => ['unit' => $share['unit']?->unit_no ?? '—', 'amount' => (float) $share['share_amount']], $shares),
            'plan_snapshot' => $plan->exists ? AssessmentPlan::findOrFail($plan->id)->getAttributes() : null,
            'snapshot' => $snapshot,
        ];
    }
}
