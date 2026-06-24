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
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->boolean('feature_auto_dues')->nullable()->after('is_active');
            $table->boolean('feature_user_portal')->nullable()->after('feature_auto_dues');
            $table->boolean('feature_reports')->nullable()->after('feature_user_portal');
            $table->boolean('feature_multi_apartment')->nullable()->after('feature_reports');
            $table->unsignedInteger('multi_apartment_limit_override')->nullable()->after('feature_multi_apartment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'feature_auto_dues',
                'feature_user_portal',
                'feature_reports',
                'feature_multi_apartment',
                'multi_apartment_limit_override',
            ]);
        });
    }
};
