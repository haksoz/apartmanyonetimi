<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->swapSupplierImportedTransactionTypes();
    }

    public function down(): void
    {
        $this->swapSupplierImportedTransactionTypes();
    }

    private function swapSupplierImportedTransactionTypes(): void
    {
        DB::table('account_transactions')
            ->where('is_imported', true)
            ->whereIn('type', ['debit', 'credit'])
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('accounts')
                    ->whereColumn('accounts.id', 'account_transactions.account_id')
                    ->where('accounts.type', 'supplier');
            })
            ->orderBy('id')
            ->chunkById(1000, function ($transactions) {
                foreach ($transactions as $transaction) {
                    DB::table('account_transactions')
                        ->where('id', $transaction->id)
                        ->update([
                            'type' => $transaction->type === 'debit' ? 'credit' : 'debit',
                        ]);
                }
            });
    }
};
