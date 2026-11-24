<?php

use App\Models\Account;
use App\Models\AccountTransaction;
use App\Support\AccountOpeningBalance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Link existing entries without writing a second ledger entry or touching cash.
            AccountTransaction::whereNull('transactionable_type')->where('description', 'Açılış bakiyesi')
                ->orderBy('id')->chunkById(100, function ($entries) {
                    foreach ($entries as $entry) {
                        $account = Account::withTrashed()->find($entry->account_id);
                        if ($account && ! $account->trashed()) {
                            app(AccountOpeningBalance::class)->save($account, (float) $entry->amount, $entry->type, $entry->transaction_date->toDateString());
                        }
                    }
                });
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            // Allocated opening sources must not be discarded by a schema rollback.
            if (DB::table('payment_allocations')->whereIn('payment_id',
                DB::table('payments')->where('is_opening', true)->select('id'))
                ->orWhereIn('due_id', DB::table('dues')->where('is_opening', true)->select('id'))
                ->orWhereIn('expense_id', DB::table('expenses')->where('is_opening', true)->select('id'))->exists()) {
                throw new RuntimeException('Önce açılış kayıtlarının eşleştirmelerini kaldırın.');
            }
            AccountTransaction::where('is_opening', true)->update(['transactionable_type' => null, 'transactionable_id' => null]);
            foreach (['dues', 'expenses', 'payments'] as $name) {
                DB::table($name)->where('is_opening', true)->delete();
            }
        });
    }
};
