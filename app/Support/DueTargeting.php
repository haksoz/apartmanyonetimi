<?php

namespace App\Support;

use App\Enums\DueType;
use App\Models\{Account, TenantAssignment, Unit};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DueTargeting
{
    public function tenants(Unit $unit, Carbon $period, bool $historical = false)
    {
        $query = TenantAssignment::query()->where('apartment_id', $unit->apartment_id)->where('unit_id', $unit->id)
            ->whereDate('move_in_date', '<=', $period->copy()->endOfMonth())
            ->where(fn ($q) => $q->whereNull('move_out_date')->orWhereDate('move_out_date', '>=', $period->copy()->startOfMonth()))
            ->with('account');
        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }
        return $query->get()->pluck('account')->filter(fn ($account) => $account
            && $account->type === Account::TYPE_TENANT && $account->apartment_id === $unit->apartment_id
            && $this->eligible($account, $period, $historical))->unique('id')->values();
    }

    public function accountFor(Unit $unit, Carbon $period, DueType $type): ?Account
    {
        if (DB::transactionLevel() > 0) {
            Unit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
        }
        if ($type === DueType::Aidat) {
            $tenants = $this->tenants($unit, $period);
            if ($tenants->count() > 1) {
                throw ValidationException::withMessages(['account_id' => "Daire {$unit->unit_no}: ilgili dönemde birden fazla kiracı var. Kiracılık tarihlerini kontrol edin."]);
            }
            if ($tenants->isNotEmpty()) {
                return $tenants->first();
            }
        }
        $owner = $unit->ownerAccount;
        return $owner && $owner->apartment_id === $unit->apartment_id && $owner->type === Account::TYPE_OWNER
            && $this->eligible($owner, $period) ? $owner : null;
    }

    public function assertAccount(Account $account, DueType $type, Carbon $period, bool $historical = false): void
    {
        if (! in_array($account->type, [Account::TYPE_OWNER, Account::TYPE_TENANT], true)
            || ! $this->eligible($account, $period, $historical)) {
            $this->reject('Hesap bu borcun döneminde kullanıma uygun değil. Hesap tarihlerini ve aktifliğini kontrol edin.');
        }
        if ($type === DueType::MalikPayi) {
            if ($account->type !== Account::TYPE_OWNER) {
                $this->reject('Kat Maliki Payı yalnızca kat maliki hesabına yazılabilir; kiracı hesabı seçilemez.');
            }
            return;
        }
        $unit = Unit::where('apartment_id', $account->apartment_id)->whereKey($account->unit_id)->first();
        if (! $unit) {
            $this->reject('Aidat için hesabın daire bağlantısı bulunmalıdır. Önce daire eşleştirmesini tamamlayın.');
        }
        if (DB::transactionLevel() > 0) {
            Unit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
        }
        $tenants = $this->tenants($unit, $period, $historical);
        if ($tenants->count() > 1) {
            $this->reject('İlgili dönemde birden fazla kiracı var. Kiracılık tarihleri netleşmeden Aidat oluşturulamaz veya aktarılamaz.');
        }
        if ($account->type === Account::TYPE_OWNER && $tenants->isNotEmpty()) {
            $this->reject('Bu dairede ilgili dönemde aktif kiracı bulunduğu için kat malikine Aidat yazılamaz. Kiracı hesabından ilerleyin veya Kat Maliki Payı seçin.');
        }
        if ($account->type === Account::TYPE_TENANT && ! $tenants->contains('id', $account->id)) {
            $this->reject('Seçilen kiracının ilgili dönemde bu dairede geçerli kiracılık kaydı bulunmuyor.');
        }
    }

    private function eligible(Account $account, Carbon $period, bool $historical = false): bool
    {
        return ! $account->trashed()
            && ($account->is_active || ($historical && $account->account_end_date))
            && (! $account->account_opening_date || $account->account_opening_date->lte($period->copy()->endOfMonth()))
            && (! $account->account_end_date || $account->account_end_date->gte($period->copy()->startOfMonth()));
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['account_id' => $message]);
    }
}
