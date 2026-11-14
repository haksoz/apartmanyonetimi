<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\TenantAssignment;
use App\Models\UnitOwnerHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetupManagerAccount
{
    public function save(Apartment $apartment, User $user, array $data): void
    {
        DB::transaction(function () use ($apartment, $user, $data) {
            $apartment = Apartment::query()->lockForUpdate()->findOrFail($apartment->id);
            if ($apartment->setup_manager_completed_at !== null || $apartment->isSetupCompleted()) {
                return;
            }

            if ($data['manager_type'] === 'external') {
                $apartment->update(['manager_unit_id' => null, 'setup_manager_completed_at' => now()]);
                return;
            }

            $unit = $apartment->units()->whereKey($data['unit_id'])->lockForUpdate()->firstOrFail();
            $owner = $apartment->accounts()->whereKey($unit->owner_account_id)->lockForUpdate()->first();
            if (! $owner || $owner->type !== Account::TYPE_OWNER || (int) $owner->unit_id !== $unit->id) {
                throw ValidationException::withMessages(['unit_id' => 'Bu dairenin kat maliki hesabı bulunamadı.']);
            }

            $identity = [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'account_opening_date' => $data['started_at'],
                'is_active' => true,
            ];

            if ($data['manager_type'] === 'tenant') {
                if ($unit->tenantAssignments()->exists()
                    || ($unit->occupant_account_id && (int) $unit->occupant_account_id !== $owner->id)) {
                    throw ValidationException::withMessages(['unit_id' => 'Bu dairede mevcut oturan veya kiracılık kaydı var. Hesaplardan kontrol edin.']);
                }
                $account = $apartment->accounts()->create($identity + ['unit_id' => $unit->id, 'type' => Account::TYPE_TENANT]);
                TenantAssignment::create([
                    'apartment_id' => $apartment->id,
                    'unit_id' => $unit->id,
                    'account_id' => $account->id,
                    'move_in_date' => $data['started_at'],
                ]);
                $unit->update(['occupant_account_id' => $account->id]);
            } else {
                if (($owner->user_id && (int) $owner->user_id !== $user->id)
                    || (filled($owner->email) && strcasecmp($owner->email, $user->email) !== 0)
                    || $owner->transactions()->exists()
                    || $unit->ownerHistories()->exists()
                    || $unit->tenantAssignments()->exists()
                    || ($unit->occupant_account_id && (int) $unit->occupant_account_id !== $owner->id)) {
                    throw ValidationException::withMessages(['unit_id' => 'Bu dairede mevcut hesap veya ilişki kaydı var. Üzerine yazılmadı; hesaplardan kontrol edin.']);
                }
                $openingDate = $owner->account_opening_date
                    ?? $apartment->account_opening_date
                    ?? Carbon::parse($data['started_at']);
                $startedAt = Carbon::parse($data['started_at']);
                if ($startedAt->lt($openingDate)) {
                    throw ValidationException::withMessages(['started_at' => 'Başlangıç tarihi mevcut kat maliki hesabının açılışından önce olamaz.']);
                }

                if ($startedAt->gt($openingDate)) {
                    // Preserve the initial owner's period rather than moving its opening date.
                    $endedAt = $startedAt->copy()->subDay()->toDateString();
                    $owner->update(['is_active' => false, 'account_end_date' => $endedAt]);
                    UnitOwnerHistory::create([
                        'apartment_id' => $apartment->id,
                        'unit_id' => $unit->id,
                        'account_id' => $owner->id,
                        'start_date' => $openingDate->toDateString(),
                        'end_date' => $endedAt,
                    ]);
                    $owner = $apartment->accounts()->create($identity + [
                        'unit_id' => $unit->id,
                        'type' => Account::TYPE_OWNER,
                    ]);
                } else {
                    $owner->update($identity);
                }
                UnitOwnerHistory::create([
                    'apartment_id' => $apartment->id,
                    'unit_id' => $unit->id,
                    'account_id' => $owner->id,
                    'start_date' => $data['started_at'],
                ]);
                $unit->update([
                    'owner_account_id' => $owner->id,
                    'occupant_account_id' => $owner->id,
                ]);
            }

            // The existing owner membership remains intact; user_id grants account portal access.
            $apartment->update(['manager_unit_id' => $unit->id, 'setup_manager_completed_at' => now()]);
        });
    }
}
