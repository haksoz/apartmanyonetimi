<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Due bağlantılı account_transactions'da transaction_date'i
        // dues.created_at_manual varsa onunla güncelle (SQLite uyumlu, cursor ile)
        DB::table('account_transactions')
            ->where('transactionable_type', 'App\\Models\\Due')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('dues')
                    ->whereColumn('dues.id', 'account_transactions.transactionable_id')
                    ->whereNotNull('dues.created_at_manual')
                    ->whereColumn('dues.created_at_manual', '!=', 'account_transactions.transaction_date');
            })
            ->orderBy('account_transactions.id')
            ->chunkById(1000, function ($transactions) {
                foreach ($transactions as $transaction) {
                    $due = DB::table('dues')->where('id', $transaction->transactionable_id)->first();
                    if ($due && $due->created_at_manual !== null) {
                        DB::table('account_transactions')
                            ->where('id', $transaction->id)
                            ->update(['transaction_date' => $due->created_at_manual]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Geri almak için due_date'e döndür
        DB::table('account_transactions')
            ->where('transactionable_type', 'App\\Models\\Due')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('dues')
                    ->whereColumn('dues.id', 'account_transactions.transactionable_id');
            })
            ->orderBy('account_transactions.id')
            ->chunkById(1000, function ($transactions) {
                foreach ($transactions as $transaction) {
                    $due = DB::table('dues')->where('id', $transaction->transactionable_id)->first();
                    if ($due) {
                        DB::table('account_transactions')
                            ->where('id', $transaction->id)
                            ->update(['transaction_date' => $due->due_date]);
                    }
                }
            });
    }
};
