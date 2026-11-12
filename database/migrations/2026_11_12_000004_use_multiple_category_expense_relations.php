<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'expense_relations')) {
            Schema::table('categories', fn (Blueprint $table) => $table->json('expense_relations')->nullable());
        }
        DB::table('categories')->whereNull('expense_relations')->orderBy('id')->each(function ($category) {
            $old = $category->default_expense_relation ?? 'unassigned';
            $values = $category->allow_expense && in_array($old, ['aidat', 'malik_payi'], true) ? [$old] : [];
            DB::table('categories')->where('id', $category->id)->update(['expense_relations' => json_encode($values)]);
        });
        if (Schema::hasColumn('categories', 'default_expense_relation')) {
            Schema::withoutForeignKeyConstraints(fn () => Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('default_expense_relation')));
        }
    }

    public function down(): void
    {
        Schema::table('categories', fn (Blueprint $table) => $table->string('default_expense_relation')->default('unassigned'));
        DB::table('categories')->orderBy('id')->each(function ($category) {
            $values = json_decode($category->expense_relations ?? '[]', true);
            DB::table('categories')->where('id', $category->id)->update([
                'default_expense_relation' => count($values) === 1 ? $values[0] : 'unassigned',
            ]);
        });
        Schema::withoutForeignKeyConstraints(fn () => Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('expense_relations')));
    }
};
