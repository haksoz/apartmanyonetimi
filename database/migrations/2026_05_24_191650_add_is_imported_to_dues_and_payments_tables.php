<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dues', function (Blueprint $table) {
            $table->boolean('is_imported')->default(false)->after('status');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->boolean('is_imported')->default(false)->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dues', function (Blueprint $table) {
            $table->dropColumn('is_imported');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('is_imported');
        });
    }
};
