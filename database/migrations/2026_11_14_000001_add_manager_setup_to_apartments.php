<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->date('account_opening_date')->nullable();
            $table->timestamp('setup_manager_completed_at')->nullable();
        });

        // Existing installations keep their current setup progress and account links.
        DB::table('apartments')->update(['setup_manager_completed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->dropColumn(['account_opening_date', 'setup_manager_completed_at']);
        });
    }
};
