<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('apartment_name')->nullable()->after('apartment_id');
            $table->foreignId('retained_user_id')->nullable()->after('apartment_name')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('retained_user_id');
            $table->dropColumn('apartment_name');
        });
    }
};
