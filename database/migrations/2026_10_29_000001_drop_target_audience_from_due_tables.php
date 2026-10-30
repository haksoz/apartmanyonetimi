<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Before running: stop writers, verify the preflight and retain an external
        // backup of the original column values. No financial rows are modified.
        foreach (['dues', 'due_batches', 'due_plans', 'assessment_plans'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('target_audience');
            });
        }
    }

    public function down(): void
    {
        // This restores schema only. Recover the original values by ID from the
        // verified backup before restarting any previous application version.
        Schema::table('dues', function (Blueprint $table) {
            $table->string('target_audience')->nullable();
        });
        foreach (['due_batches', 'due_plans'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('target_audience')->default('tenant_priority');
            });
        }
        Schema::table('assessment_plans', function (Blueprint $table) {
            $table->string('target_audience')->default('owner_only');
        });
    }
};
