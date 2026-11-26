<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Category;
use App\Models\Due;
use App\Models\DueBatch;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsumptionBatchTracking
{
    public function summary(DueBatch $batch, ?int $unitId = null): array
    {
        $snapshot = $batch->distribution_snapshot ?? [];
        $activeQuery = $batch->dues()->with('account');
        $archivedQuery = $batch->dues()->onlyTrashed()->orderByDesc('id');
        if (DB::transactionLevel() > 0) {
            $activeQuery->lockForUpdate();
            $archivedQuery->lockForUpdate();
        }
        $active = $activeQuery->get()->groupBy('unit_id');
        $archived = $archivedQuery->get()->groupBy('unit_id');
        $rows = [];
        $missing = 0;
        $expected = 0;
        $existing = 0;
        $hasError = false;
        $originalRows = collect($snapshot['rows'] ?? []);
        if ($unitId !== null) {
            $originalRows = $originalRows->where('unit_id', $unitId);
            abort_if($originalRows->isEmpty(), 404);
        }
        foreach ($originalRows as $original) {
            $expectedCents = (int) ($original['amount_cents'] ?? 0);
            $dues = $active->get($original['unit_id'], collect());
            $due = $dues->first();
            $deleted = $archived->get($original['unit_id'], collect())->first();
            $fields = $snapshot['debt_fields'] ?? ($deleted ? [
                'category_id' => $deleted->category_id, 'due_date' => $deleted->due_date?->format('Y-m-d'),
                'created_at_manual' => $deleted->created_at_manual?->format('Y-m-d'), 'description' => $deleted->description,
            ] : null);
            $error = null;
            $needsDebt = $expectedCents > 0;
            if ($needsDebt) {
                $expected++;
                if ($due) {
                    $existing++;
                } else {
                    $missing++;
                    $account = Account::where('apartment_id', $batch->apartment_id)->find($original['account_id']);
                    $unit = Unit::where('apartment_id', $batch->apartment_id)->find($original['unit_id']);
                    if (! $account || ! $unit || (int) $account->unit_id !== (int) $unit->id) {
                        $error = 'İlk borçlandırmadaki hesap veya daire bağlantısı bulunamıyor; otomatik hesap değişikliği yapılmaz.';
                    } elseif (! $fields || ! $fields['due_date'] || ! $fields['created_at_manual']) {
                        $error = 'İlk borcun tarih ve kategori bilgileri bulunamıyor.';
                    } elseif ($fields['category_id'] && ! Category::withTrashed()->where('apartment_id', $batch->apartment_id)->whereKey($fields['category_id'])->exists()) {
                        $error = 'İlk borcun kategorisi bulunamıyor.';
                    } elseif ($deleted && ($deleted->allocations()->exists() || in_array($deleted->status, ['paid', 'partial'], true))) {
                        $error = 'Silinmiş borçta tahsilat bilgisi var; otomatik tamamlama yapılamaz.';
                    }
                }
            }
            if ($dues->count() > 1) {
                $error = 'Bu daire için birden fazla mevcut borç var; kayıtları kontrol edin.';
            }
            $hasError = $hasError || $error !== null;
            $rows[] = ['unit_id' => $original['unit_id'], 'label' => $original['label'],
                'account_id' => $original['account_id'], 'account_name' => $original['account_name'],
                'consumption' => $original['consumption'], 'expected_cents' => $expectedCents,
                'due_id' => $due?->id, 'actual_account_name' => $due?->account?->name,
                'actual_cents' => (int) round($dues->sum('amount') * 100), 'debt_status' => $due?->status,
                'missing' => $needsDebt && ! $due, 'fields' => $fields, 'error' => $error];
        }
        $cancelled = $batch->consumption_cancelled_at !== null;

        return ['batch_id' => $batch->id, 'period_id' => $batch->consumption_period_id,
            'status' => $cancelled ? 'İptal edildi' : ($missing ? 'Eksik' : ($expected ? 'Tamamlandı' : 'Takip bilgisi eksik')),
            'cancelled' => $cancelled, 'expected_count' => $expected, 'existing_count' => $existing, 'missing_count' => $missing,
            'original_cents' => (int) ($snapshot['billed_cents'] ?? round($batch->source_amount * 100)),
            'actual_cents' => (int) round($active->flatten()->sum('amount') * 100),
            'repair_cents' => array_sum(array_map(fn ($row) => $row['missing'] ? $row['expected_cents'] : 0, $rows)),
            'can_repair' => ! $cancelled && $missing > 0 && ! $hasError, 'rows' => $rows];
    }

    public function repair(DueBatch $batch, array $summary): int
    {
        if (! $summary['can_repair'] || $batch->consumption_cancelled_at) {
            throw ValidationException::withMessages(['batch' => 'Bu borçlandırma tamamlanabilir durumda değil.']);
        }
        $count = 0;
        foreach ($summary['rows'] as $row) {
            if (! $row['missing']) {
                continue;
            }
            $fields = $row['fields'];
            $due = Due::create([
                'apartment_id' => $batch->apartment_id, 'due_batch_id' => $batch->id,
                'unit_id' => $row['unit_id'], 'account_id' => $row['account_id'], 'due_type' => $batch->due_type,
                'category_id' => $fields['category_id'], 'due_date' => $fields['due_date'],
                'created_at_manual' => $fields['created_at_manual'], 'description' => $fields['description'],
                'amount' => $row['expected_cents'] / 100, 'remaining_amount' => $row['expected_cents'] / 100, 'status' => 'unpaid',
            ]);
            AccountTransaction::create([
                'apartment_id' => $batch->apartment_id, 'account_id' => $row['account_id'],
                'transactionable_type' => Due::class, 'transactionable_id' => $due->id,
                'type' => 'debit', 'amount' => $row['expected_cents'] / 100,
                'description' => $fields['description'] ?? $batch->due_type_label.' borçlandırması',
                'transaction_date' => $fields['created_at_manual'],
            ]);
            $count++;
        }

        return $count;
    }
}
