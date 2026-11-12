<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->string('default_expense_relation')->default('unassigned'));
        DB::table('categories')->whereIn('name', [
            'Ortak Elektrik', 'Ortak Su', 'Ortak Temizlik', 'Ortak Asansör', 'Yönetim Giderleri',
        ])->update(['default_expense_relation' => 'aidat']);
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('default_expense_relation'));
    }
};
