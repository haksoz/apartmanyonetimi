<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_transactions', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('transaction_date');
            }

            if (! Schema::hasColumn('cash_transactions', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('cash_transactions', 'is_active')) {
                $table->dropColumn('is_active');
            }

            if (Schema::hasColumn('cash_transactions', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
