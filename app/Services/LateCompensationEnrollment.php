<?php

namespace App\Services;

use App\Models\Apartment;
use App\Models\Due;
use App\Support\DueProductionSource;
use App\Support\LateCompensationScope;
use Illuminate\Validation\ValidationException;

class LateCompensationEnrollment
{
    public function __construct(private readonly LateCompensationScope $scope) {}

    public function preview(Apartment $apartment, array $input): array
    {
        if (empty($input['is_active']) || $apartment->lateCompensationPolicies()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages([
                'late_compensation.include_open' => 'Mevcut açık borçlar yalnızca ilk etkinleştirmede seçilebilir.',
            ]);
        }
        $start = $apartment->late_compensation_starts_on?->toDateString() ?? ($input['starts_on'] ?? null);
        $activation = $apartment->late_compensation_starts_on ? ($input['effective_from'] ?? null) : $start;
        if ($start === null || $activation === null || $activation < now('Europe/Istanbul')->toDateString()) {
            throw ValidationException::withMessages(['late_compensation.starts_on' => 'Önizleme için geçerli bir uygulama tarihi girin.']);
        }

        $rows = [];
        foreach (Due::where('apartment_id', $apartment->id)->where('remaining_amount', '>', 0)
            ->whereNotIn('status', ['paid'])->where('late_compensation_applies', false)->with('batch')->orderBy('id')->get() as $due) {
            $source = DueProductionSource::forRecordedDue($due);
            [$principal, $issue] = $this->openingBalance($due, $start);
            $reason = match (true) {
                $due->is_opening => 'Açılış kaydı kapsam dışında.',
                $source === null && ! $this->scope->allowsUnknownSource($due->due_type, $due->category_id) => 'Bu borç türünde kaynak kısıtlaması var ve üretim kaynağı doğrulanamıyor.',
                $source !== null && ! $this->scope->allows($source, $due->due_type, $due->category_id) => 'Merkezi uygulama kapsamı dışında.',
                default => $issue,
            };

            $rows[] = [
                'id' => $due->id, 'label' => $due->reference_number ?: $due->id, 'account' => $due->account_id,
                'principal' => LateCompensationCalculator::money($principal), 'reason' => $reason,
            ];
        }

        return ['start' => $start, 'activation' => $activation, 'rows' => $rows];
    }

    public function enroll(Apartment $apartment, array $preview): void
    {
        $eligible = collect($preview['rows'])->whereNull('reason');
        foreach ($eligible->groupBy('account')->sortKeys() as $account => $rows) {
            $ids = $rows->pluck('id');
            Due::where('apartment_id', $apartment->id)->where('account_id', $account)->whereIn('id', $ids)->update([
                'late_compensation_applies' => true,
                'late_compensation_starts_on' => $preview['start'],
            ]);
            app(DueCollectionReplay::class)->run($apartment->id, (int) $account, $preview['start']);
        }
    }

    private function openingBalance(Due $due, string $start): array
    {
        if ($due->account_id === null || $due->due_date === null || $due->created_at === null) {
            return [0, 'Cari hesap, vade veya oluşturulma tarihi eksik.'];
        }
        if ($due->is_imported && ($due->created_at_manual === null || ! $due->transactions()->where('type', 'debit')
            ->where('amount', $due->amount)->where('account_id', $due->account_id)->exists())) {
            return [0, 'İçe aktarılan borcun ana para geçmişi doğrulanamıyor.'];
        }
        $principal = LateCompensationCalculator::cents($due->amount);
        $paid = $prior = 0;
        foreach ($due->allocations()->with('payment')->get() as $allocation) {
            $payment = $allocation->payment;
            if (! $payment || $payment->apartment_id !== $due->apartment_id || $payment->account_id !== $due->account_id
                || ! $payment->payment_date || $allocation->expense_id !== null
                || LateCompensationCalculator::cents($allocation->amount) <= 0) {
                return [0, 'Tahsilat geçmişi doğrulanamıyor.'];
            }
            $applied = LateCompensationCalculator::cents($allocation->principal_amount);
            if ($payment->payment_date->toDateString() < $start) {
                $prior += $applied;
            }
            $paid += $applied;
        }
        if ($principal - $paid !== LateCompensationCalculator::cents($due->remaining_amount)) {
            return [0, 'Kalan ana para tahsis geçmişiyle uyuşmuyor.'];
        }

        return [$principal - $prior, null];
    }
}
