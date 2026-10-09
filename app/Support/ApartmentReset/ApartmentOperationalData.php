<?php

namespace App\Support\ApartmentReset;

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Apartment;
use App\Models\CashBox;
use App\Models\Category;
use App\Models\CashTransaction;
use App\Models\Due;
use App\Models\DueBatch;
use App\Models\DuePlan;
use App\Models\Expense;
use App\Models\ExpenseDocument;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\TenantAssignment;
use App\Models\Unit;
use App\Models\UnitOwnerHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

    /** Test, transaction kapanmadan önce hata verir. Canlıda boştur. */
    public static ?\Closure $beforeCommit = null;

    public function renewSetup(Apartment $apartment, int $unitCount, User $actor): void
    {
        $documentPaths = [];

        DB::transaction(function () use ($apartment, $unitCount, $actor, &$documentPaths) {
            $paths = $this->detachExpenseDocuments($apartment);
            $this->wipe($apartment);

            CashBox::where('apartment_id', $apartment->id)->delete();
            TenantAssignment::where('apartment_id', $apartment->id)->delete();
            UnitOwnerHistory::where('apartment_id', $apartment->id)->delete();

            $this->replaceUnits($apartment, $unitCount);
            $this->replaceCategories($apartment);
            $this->removeOtherMemberships($apartment, $actor);

            $apartment->update([
                'unit_count' => $unitCount,
                'setup_units_completed_at' => null,
                'setup_completed_at' => null,
            ]);

            if (self::$beforeCommit) {
                (self::$beforeCommit)();
            }

            $documentPaths = $paths;
        });

        $this->deleteStoredDocuments($documentPaths);
    }

    private function detachExpenseDocuments(Apartment $apartment): array
    {
        $expenseIds = Expense::withTrashed()
            ->where('apartment_id', $apartment->id)
            ->pluck('id');

        $paths = ExpenseDocument::withTrashed()
            ->whereIn('expense_id', $expenseIds)
            ->pluck('file_path')
            ->filter()
            ->values()
            ->all();

        ExpenseDocument::query()
            ->whereIn('expense_id', $expenseIds)
            ->delete();

        return $paths;
    }

    private function deleteStoredDocuments(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk('public')->delete($path);
        }
    }

    private function replaceUnits(Apartment $apartment, int $unitCount): void
    {
        $openingDate = Account::query()
            ->where('apartment_id', $apartment->id)
            ->value('account_opening_date') ?? now()->toDateString();

        Unit::query()->where('apartment_id', $apartment->id)->update([
            'owner_account_id' => null,
            'occupant_account_id' => null,
        ]);
        Account::query()->where('apartment_id', $apartment->id)->delete();
        Unit::query()->where('apartment_id', $apartment->id)->delete();

        for ($i = 1; $i <= $unitCount; $i++) {
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
                'user_id' => null,
                'account_opening_date' => $openingDate,
            ]);
            $unit->update([
                'owner_account_id' => $ownerAccount->id,
                'occupant_account_id' => $ownerAccount->id,
            ]);
        }
    }

    private function replaceCategories(Apartment $apartment): void
    {
        Category::withTrashed()->where('apartment_id', $apartment->id)->forceDelete();
        Category::createDefaultsFor($apartment->id);
    }

    private function removeOtherMemberships(Apartment $apartment, User $actor): void
    {
        $others = $apartment->members()->where('users.id', '!=', $actor->id)->pluck('users.id');

        if ($others->isNotEmpty()) {
            $apartment->members()->detach($others->all());
        }
    }
}
