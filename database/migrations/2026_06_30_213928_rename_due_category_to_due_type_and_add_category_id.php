<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['dues', 'due_batches', 'due_plans'] as $tbl) {
            Schema::table($tbl, function (Blueprint $t) use ($tbl) {
                // 1. due_category → due_type rename
                if (Schema::hasColumn($tbl, 'due_category') && ! Schema::hasColumn($tbl, 'due_type')) {
                    $t->renameColumn('due_category', 'due_type');
                }
            });
        }

        foreach (['dues', 'due_batches', 'due_plans'] as $tbl) {
            Schema::table($tbl, function (Blueprint $t) use ($tbl) {
                // 2. category_id (nullable FK) ekle
                if (! Schema::hasColumn($tbl, 'category_id')) {
                    $t->foreignId('category_id')->nullable()->after('due_type')->constrained()->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['dues', 'due_batches', 'due_plans'] as $tbl) {
            Schema::table($tbl, function (Blueprint $t) use ($tbl) {
                if (Schema::hasColumn($tbl, 'category_id')) {
                    $t->dropForeign($tbl . '_category_id_foreign');
                    $t->dropColumn('category_id');
                }
            });
        }

        foreach (['dues', 'due_batches', 'due_plans'] as $tbl) {
            Schema::table($tbl, function (Blueprint $t) use ($tbl) {
                if (Schema::hasColumn($tbl, 'due_type') && ! Schema::hasColumn($tbl, 'due_category')) {
                    $t->renameColumn('due_type', 'due_category');
                }
            });
        }
    }
};
