<?php

namespace App\Support\ApartmentReset;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Apartment;
use App\Models\CashBox;
use App\Models\CashTransaction;
use App\Models\Due;
use App\Models\DueBatch;
use App\Models\DuePlan;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\TenantAssignment;
use App\Models\Unit;
use App\Models\UnitOwnerHistory;
use Illuminate\Support\Facades\DB;

class ApartmentOperationalData
{
    public function wipe(Apartment $apartment): void
    {
        PaymentAllocation::whereHas('payment', function ($query) use ($apartment) {
            $query->where('apartment_id', $apartment->id);
        })->delete();

        CashTransaction::where('apartment_id', $apartment->id)->delete();
        Payment::where('apartment_id', $apartment->id)->delete();
        Due::where('apartment_id', $apartment->id)->delete();
        DueBatch::where('apartment_id', $apartment->id)->delete();
        DuePlan::where('apartment_id', $apartment->id)->delete();
        Expense::where('apartment_id', $apartment->id)->delete();
        AccountTransaction::where('apartment_id', $apartment->id)->delete();
    }

    public function renewSetup(Apartment $apartment, int $unitCount): void
    {
        DB::transaction(function () use ($apartment, $unitCount) {
            $this->wipe($apartment);

            CashBox::where('apartment_id', $apartment->id)->delete();
            TenantAssignment::where('apartment_id', $apartment->id)->delete();
            UnitOwnerHistory::where('apartment_id', $apartment->id)->delete();

            $this->resizeUnits($apartment, $unitCount);

            Unit::where('apartment_id', $apartment->id)->update([
                'floor' => null,
                'block' => null,
                'resident_name' => null,
                'phone' => null,
                'square_meters' => null,
                'share_coefficient' => null,
            ]);

            $apartment->update([
                'unit_count' => $unitCount,
                'setup_units_completed_at' => null,
                'setup_completed_at' => null,
            ]);
        });
    }

    private function resizeUnits(Apartment $apartment, int $unitCount): void
    {
        $openingDate = Account::query()
            ->where('apartment_id', $apartment->id)
            ->value('account_opening_date') ?? now()->toDateString();

        $units = Unit::query()
            ->where('apartment_id', $apartment->id)
            ->orderByRaw('CAST(unit_no AS UNSIGNED)')
            ->get();

        foreach ($units as $unit) {
            $number = (int) $unit->unit_no;
            if ($number <= $unitCount) {
                continue;
            }

            $unit->update([
                'owner_account_id' => null,
                'occupant_account_id' => null,
            ]);
            Account::query()->where('unit_id', $unit->id)->delete();
            $unit->delete();
        }

        $existing = Unit::query()->where('apartment_id', $apartment->id)->count();

        for ($i = $existing + 1; $i <= $unitCount; $i++) {
            $unitNo = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $unit = Unit::create([
                'apartment_id' => $apartment->id,
                'unit_no' => $unitNo,
            ]);
            $ownerAccount = Account::create([
                'apartment_id' => $apartment->id,
                'unit_id' => $unit->id,
                'type' => Account::TYPE_OWNER,
                'name' => $unitNo.'. Daire Kat Maliki',
                'account_opening_date' => $openingDate,
            ]);
            $unit->update([
                'owner_account_id' => $ownerAccount->id,
                'occupant_account_id' => $ownerAccount->id,
            ]);
        }
    }
}
