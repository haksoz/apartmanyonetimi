<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->timestamp('setup_units_completed_at')->nullable()->after('unit_count');
            $table->timestamp('setup_completed_at')->nullable()->after('setup_units_completed_at');
        });

        // Mevcut apartmanlar kurulumu tamamlanmis kabul edilir.
        DB::table('apartments')->whereNull('setup_completed_at')->update([
            'setup_completed_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->dropColumn(['setup_units_completed_at', 'setup_completed_at']);
        });
    }
};
