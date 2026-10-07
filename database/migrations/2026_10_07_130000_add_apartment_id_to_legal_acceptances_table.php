<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_acceptances', function (Blueprint $table) {
            $table->foreignId('apartment_id')->nullable()->after('user_subscription_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legal_acceptances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('apartment_id');
        });
    }
};
