<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('due_plans', function (Blueprint $table) {
            $table->dropUnique('due_plans_apartment_year_unique');
            $table->unsignedSmallInteger('active_plan_year')->nullable()
                ->virtualAs('CASE WHEN is_active = 1 THEN plan_year ELSE NULL END');
            $table->unique(['apartment_id', 'active_plan_year'], 'due_plans_active_year_unique');
        });
    }

    public function down(): void
    {
        Schema::table('due_plans', function (Blueprint $table) {
            $table->dropUnique('due_plans_active_year_unique');
            $table->dropColumn('active_plan_year');
            $table->unique(['apartment_id', 'plan_year'], 'due_plans_apartment_year_unique');
        });
    }
};
