<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('due_plans', function (Blueprint $table) {
            $table->decimal('per_unit_amount', 10, 2)->nullable()->after('yearly_amount');
        });
    }

    public function down(): void
    {
        Schema::table('due_plans', function (Blueprint $table) {
            $table->dropColumn('per_unit_amount');
        });
    }
};
