<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_plans', function (Blueprint $table) {
            $table->string('generation_mode')->default('on_schedule');
        });
    }

    public function down(): void
    {
        Schema::table('expense_plans', function (Blueprint $table) {
            $table->dropColumn('generation_mode');
        });
    }
};
