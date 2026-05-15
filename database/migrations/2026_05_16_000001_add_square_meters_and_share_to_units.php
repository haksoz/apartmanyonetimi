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
        Schema::table('units', function (Blueprint $table) {
            $table->decimal('square_meters', 8, 2)->nullable()->after('unit_no')->comment('Metrekare');
            $table->decimal('share_coefficient', 8, 4)->nullable()->after('square_meters')->comment('Pay çarpanı');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['square_meters', 'share_coefficient']);
        });
    }
};
