<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->decimal('subscription_tax_rate', 5, 2)->default(20)->after('fallback_package_id');
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->decimal('net_amount', 10, 2)->nullable()->after('price');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('net_amount');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
        });

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->decimal('net_amount', 10, 2)->nullable()->after('amount');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('net_amount');
            $table->decimal('tax_amount', 10, 2)->default(0)->after('tax_rate');
        });

        DB::table('user_subscriptions')->whereNull('net_amount')->update(['net_amount' => DB::raw('price')]);
        DB::table('subscription_items')->whereNull('net_amount')->update(['net_amount' => DB::raw('amount')]);
    }

    public function down(): void
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropColumn(['net_amount', 'tax_rate', 'tax_amount']);
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['net_amount', 'tax_rate', 'tax_amount']);
        });

        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('subscription_tax_rate');
        });
    }
};
