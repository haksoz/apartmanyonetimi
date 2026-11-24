<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['dues', 'expenses', 'payments', 'account_transactions'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->boolean('is_opening')->default(false)->index());
        }
    }

    public function down(): void
    {
        foreach (['dues', 'expenses', 'payments', 'account_transactions'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropIndex($name.'_is_opening_index');
                $table->dropColumn('is_opening');
            });
        }
    }
};
