<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('account_transactions', 'transactionable_type')) {
                $table->string('transactionable_type')->nullable()->after('account_id');
            }
            if (! Schema::hasColumn('account_transactions', 'transactionable_id')) {
                $table->unsignedBigInteger('transactionable_id')->nullable()->after('transactionable_type');
            }
            $table->index(['transactionable_type', 'transactionable_id'], 'account_transactions_transactionable_index');
        });
    }

    public function down(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->dropIndex('account_transactions_transactionable_index');
            if (Schema::hasColumn('account_transactions', 'transactionable_type')) {
                $table->dropColumn('transactionable_type');
            }
            if (Schema::hasColumn('account_transactions', 'transactionable_id')) {
                $table->dropColumn('transactionable_id');
            }
        });
    }
};
