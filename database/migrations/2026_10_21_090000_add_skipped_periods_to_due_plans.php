<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('due_plans', function (Blueprint $table) {
            $table->json('skipped_periods')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('due_plans', function (Blueprint $table) {
            $table->dropColumn('skipped_periods');
        });
    }
};
