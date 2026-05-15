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
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->string('reference_number', 30)->nullable()->after('id');
            $table->unique(['apartment_id', 'reference_number'], 'cash_tx_apartment_ref_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropUnique('cash_tx_apartment_ref_unique');
            $table->dropColumn('reference_number');
        });
    }
};
