<?php

namespace App\Support;

use App\Models\AccountTransaction;
use App\Models\Apartment;
use App\Models\CashTransaction;
use App\Models\Due;
use App\Models\Expense;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;

class BulkImportGuard
{
    public function reason(Apartment $apartment): ?string
    {
        $found = $this->findings($apartment->id);

        if ($found === []) {
            return null;
        }

        return 'İçe aktarma kapalı. Bu apartmanda içe aktarma dışında kayıt var: '
            .implode(', ', $found)
            .'. Excel bu kayıtların üzerine ekleneceği için bakiye ve kasa iki kez yazılır.';
    }

    public function redirectIfBlocked(Apartment $apartment): ?RedirectResponse
    {
        $reason = $this->reason($apartment);

        return $reason === null
            ? null
            : redirect()->route('apartments.index')->with('error', $reason);
    }

    public function deleteReason(Apartment $apartment): ?string
    {
        if (! $this->hasImported($apartment->id)) {
            return null;
        }

        $found = $this->findings($apartment->id);

        if ($found === []) {
            return null;
        }

        return 'İçe aktarılanlar silinemez. İçe aktarma yapıldıktan sonra bu apartmanda işlem girilmiş: '
            .implode(', ', $found)
            .'. Bu işlemler dururken devir kayıtlarını silmek bakiyeyi bozar.';
    }

    public function redirectIfDeleteBlocked(Apartment $apartment): ?RedirectResponse
    {
        $reason = $this->deleteReason($apartment);

        return $reason === null
            ? null
            : redirect()->route('apartments.index')->with('error', $reason);
    }

    private function hasImported(int $apartmentId): bool
    {
        return AccountTransaction::query()->where('apartment_id', $apartmentId)->where('is_imported', true)->exists()
            || Due::query()->where('apartment_id', $apartmentId)->where('is_imported', true)->exists()
            || Payment::query()->where('apartment_id', $apartmentId)->where('is_imported', true)->exists()
            || Expense::query()->where('apartment_id', $apartmentId)->where('is_imported', true)->exists();
    }

    /**
     * @return list<string>
     */
    private function findings(int $apartmentId): array
    {
        $found = [];

        if (AccountTransaction::query()
            ->where('apartment_id', $apartmentId)
            ->where('is_imported', false)
            ->where('description', 'Açılış bakiyesi')
            ->exists()) {
            $found[] = 'hesap açılış bakiyesi';
        }

        if (Due::query()->where('apartment_id', $apartmentId)->where('is_imported', false)->exists()) {
            $found[] = 'aidat';
        }

        if (Payment::query()->where('apartment_id', $apartmentId)->where('is_imported', false)->exists()) {
            $found[] = 'tahsilat veya ödeme';
        }

        if (Expense::query()->where('apartment_id', $apartmentId)->where('is_imported', false)->exists()) {
            $found[] = 'gider';
        }

        if (CashTransaction::query()
            ->where('apartment_id', $apartmentId)
            ->whereNull('payment_id')
            ->whereNull('expense_id')
            ->where('description', 'Açılış bakiyesi')
            ->exists()) {
            $found[] = 'kasa açılış bakiyesi';
        }

        if (CashTransaction::query()
            ->where('apartment_id', $apartmentId)
            ->whereNull('payment_id')
            ->whereNull('expense_id')
            ->where(function ($query) {
                $query->whereNull('description')->orWhere('description', '!=', 'Açılış bakiyesi');
            })
            ->exists()) {
            $found[] = 'kasa hareketi';
        }

        if (AccountTransaction::query()
            ->where('apartment_id', $apartmentId)
            ->where('is_imported', false)
            ->where(function ($query) {
                $query->whereNull('description')->orWhere('description', '!=', 'Açılış bakiyesi');
            })
            ->where(function ($query) {
                $query->whereNull('transactionable_type')
                    ->orWhereNotIn('transactionable_type', [Due::class, Payment::class, Expense::class]);
            })
            ->exists()) {
            $found[] = 'cari hareket';
        }

        return $found;
    }
}
