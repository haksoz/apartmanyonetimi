<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. due_category alanını üç tabloya ekle (yoksa)
        foreach (['dues', 'due_batches', 'due_plans'] as $tbl) {
            if (! Schema::hasColumn($tbl, 'due_category')) {
                Schema::table($tbl, function (Blueprint $t) {
                    $t->string('due_category')->nullable()->after('category_id');
                });
            }
        }

        // 2. Veri taşıma: category adına göre sabit değere mapla
        $mapping = [
            'Malik Payı'      => 'malik_payi',
            'Masraf Yansıtma' => 'masraf_yansitma',
            'Ceza'            => 'ceza',
            'Faiz'            => 'faiz',
        ];

        foreach (['dues', 'due_batches', 'due_plans'] as $tbl) {
            $rows = DB::table($tbl)
                ->leftJoin('categories', 'categories.id', '=', $tbl . '.category_id')
                ->select($tbl . '.id', 'categories.name as cat_name')
                ->get();

            foreach ($rows as $row) {
                $value = $mapping[$row->cat_name] ?? 'aidat';
                DB::table($tbl)->where('id', $row->id)->update(['due_category' => $value]);
            }
        }

        // 3. due_category NOT NULL + default yap
        foreach (['dues', 'due_batches', 'due_plans'] as $tbl) {
            Schema::table($tbl, function (Blueprint $t) {
                $t->string('due_category')->nullable(false)->default('aidat')->change();
            });
        }

        // 4. FK constraint kaldır, sonra category_id sütununu sil
        $fkMap = [
            'dues'        => 'dues_category_id_foreign',
            'due_batches' => 'due_batches_category_id_foreign',
            'due_plans'   => 'due_plans_category_id_foreign',
        ];

        foreach ($fkMap as $tbl => $fk) {
            Schema::table($tbl, function (Blueprint $t) use ($tbl, $fk) {
                if (Schema::hasColumn($tbl, 'category_id')) {
                    try { $t->dropForeign($fk); } catch (\Throwable $e) {}
                    $t->dropColumn('category_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['dues', 'due_batches', 'due_plans'] as $tbl) {
            Schema::table($tbl, function (Blueprint $t) {
                $t->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        foreach (['dues', 'due_batches', 'due_plans'] as $tbl) {
            Schema::table($tbl, function (Blueprint $t) {
                $t->dropColumn('due_category');
            });
        }
    }
};
