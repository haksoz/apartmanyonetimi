<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('management_offers', function (Blueprint $table) {
            $table->timestamp('transfer_confirmed_at')->nullable();
            $table->json('previous_manager_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('management_offers', function (Blueprint $table) {
            $table->dropColumn(['transfer_confirmed_at', 'previous_manager_ids']);
        });
    }
};
