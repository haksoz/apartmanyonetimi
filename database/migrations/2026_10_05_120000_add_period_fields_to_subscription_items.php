<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->string('plan', 10)->nullable()->after('currency');
            $table->timestamp('started_at')->nullable()->after('plan');
            $table->timestamp('expires_at')->nullable()->after('started_at');
            $table->string('status', 20)->nullable()->after('expires_at');
            $table->timestamp('ended_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropColumn(['plan', 'started_at', 'expires_at', 'status', 'ended_at']);
        });
    }
};
