<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Due bağlantılı account_transactions'da transaction_date'i
        // dues.created_at_manual varsa onunla, yoksa due_date ile güncelle
        DB::statement("
            UPDATE account_transactions
            INNER JOIN dues ON dues.id = account_transactions.transactionable_id
            SET account_transactions.transaction_date = COALESCE(dues.created_at_manual, dues.due_date)
            WHERE account_transactions.transactionable_type = 'App\\\\Models\\\\Due'
              AND dues.created_at_manual IS NOT NULL
              AND dues.created_at_manual != account_transactions.transaction_date
        ");
    }

    public function down(): void
    {
        // Geri almak için due_date'e döndür
        DB::statement("
            UPDATE account_transactions
            INNER JOIN dues ON dues.id = account_transactions.transactionable_id
            SET account_transactions.transaction_date = dues.due_date
            WHERE account_transactions.transactionable_type = 'App\\\\Models\\\\Due'
        ");
    }
};
