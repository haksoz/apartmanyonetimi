<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('due_batches', function (Blueprint $table) {
            $table->string('target_audience')->default('tenant_priority')->after('distribution_type');
        });
    }

    public function down(): void
    {
        Schema::table('due_batches', function (Blueprint $table) {
            $table->dropColumn('target_audience');
        });
    }
};
