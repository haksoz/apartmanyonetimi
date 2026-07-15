<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('due_plans', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('year');
            $table->date('end_date')->nullable()->after('start_date');
        });

        DB::table('due_plans')->delete();

        if (Schema::hasColumn('due_plans', 'year')) {
            Schema::table('due_plans', function (Blueprint $table) {
                $table->dropColumn('year');
            });
        }
    }

    public function down(): void
    {
        Schema::table('due_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->nullable()->after('due_type');
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
