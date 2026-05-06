<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->date('period_month')->nullable()->after('expense_date');
        });

        foreach (DB::table('expenses')->whereNull('period_month')->whereNotNull('expense_date')->get(['id', 'expense_date']) as $expense) {
            DB::table('expenses')
                ->where('id', $expense->id)
                ->update(['period_month' => substr($expense->expense_date, 0, 7).'-01']);
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('period_month');
        });
    }
};
