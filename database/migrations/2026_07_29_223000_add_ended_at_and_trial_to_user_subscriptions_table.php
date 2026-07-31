<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->timestamp('ended_at')->nullable()->after('expires_at');
            $table->boolean('is_trial')->default(false)->after('is_active');
            $table->text('notes')->nullable()->after('is_trial');
        });
    }

    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['ended_at', 'is_trial', 'notes']);
        });
    }
};
