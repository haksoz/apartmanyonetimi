<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dues', function (Blueprint $table) {
            $table->string('assigned_production_source', 48)->nullable();
            $table->unsignedBigInteger('production_source_assigned_by')->nullable();
            $table->timestamp('production_source_assigned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dues', function (Blueprint $table) {
            $table->dropColumn(['assigned_production_source', 'production_source_assigned_by', 'production_source_assigned_at']);
        });
    }
};
