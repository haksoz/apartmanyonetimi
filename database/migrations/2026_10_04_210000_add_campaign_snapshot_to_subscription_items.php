<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->decimal('list_amount', 10, 2)->nullable()->after('amount');
            $table->string('campaign_name')->nullable()->after('list_amount');
            $table->decimal('discount_amount', 10, 2)->nullable()->after('campaign_name');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropColumn(['list_amount', 'campaign_name', 'discount_amount']);
        });
    }
};
