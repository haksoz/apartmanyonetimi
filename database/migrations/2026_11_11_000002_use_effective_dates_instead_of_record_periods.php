<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('expenses')->whereNull('expense_date')->exists()
            || DB::table('dues')->whereNull('created_at_manual')->whereNull('created_at')->exists()) {
            throw new RuntimeException('Esas tarihi eksik gider/borç var. Dönem kaldırılmadan önce kayıtlar incelenmelidir.');
        }
        if ((Schema::hasColumn('expenses', 'period_month') && DB::table('expenses')->whereNotNull('period_month')
                ->whereRaw('SUBSTR(period_month, 1, 7) <> SUBSTR(expense_date, 1, 7)')->exists())
            || (Schema::hasColumn('dues', 'period') && DB::table('dues')->whereNotNull('period')->where('period', '!=', '')
                ->whereRaw('SUBSTR(period, 1, 7) <> SUBSTR(COALESCE(created_at_manual, created_at), 1, 7)')->exists())) {
            throw new RuntimeException('Tarih ile eski dönem arasında fark var. Veri kontrolü tamamlanmadan dönem sütunları kaldırılamaz.');
        }

        if (! Schema::hasColumn('due_batches', 'assessment_date')) {
            Schema::table('due_batches', fn (Blueprint $table) => $table->date('assessment_date')->nullable()->index());
        }
        DB::table('due_batches')->whereNull('assessment_date')->orderBy('id')->each(function ($batch) {
            $due = DB::table('dues')->where('due_batch_id', $batch->id)->orderBy('id')->first(['created_at_manual', 'created_at']);
            $date = $due?->created_at_manual ?? $due?->created_at;
            $date ??= ($batch->period ?? null) ? substr($batch->period, 0, 7).'-01' : $batch->created_at;
            if (! $date) throw new RuntimeException('Borçlandırma grubu tarihi belirlenemedi: '.$batch->id);
            DB::table('due_batches')->where('id', $batch->id)->update(['assessment_date' => substr($date, 0, 10)]);
        });
        foreach (['expenses' => 'period_month', 'dues' => 'period', 'due_batches' => 'period'] as $table => $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($column));
            }
        }
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $table) => $table->date('period_month')->nullable());
        Schema::table('dues', fn (Blueprint $table) => $table->string('period')->nullable());
        Schema::table('due_batches', fn (Blueprint $table) => $table->string('period')->nullable());
        DB::table('expenses')->orderBy('id')->each(fn ($row) => DB::table('expenses')->where('id', $row->id)
            ->update(['period_month' => substr($row->expense_date, 0, 7).'-01']));
        DB::table('dues')->orderBy('id')->each(fn ($row) => DB::table('dues')->where('id', $row->id)
            ->update(['period' => substr($row->created_at_manual ?? $row->created_at, 0, 7)]));
        DB::table('due_batches')->orderBy('id')->each(fn ($row) => DB::table('due_batches')->where('id', $row->id)
            ->update(['period' => substr($row->assessment_date, 0, 7)]));
        Schema::table('due_batches', function (Blueprint $table) {
            $table->dropIndex(['assessment_date']);
            $table->dropColumn('assessment_date');
        });
    }
};
