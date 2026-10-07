<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
        });

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_id')->nullable()->change();
        });

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->foreign('subscription_id')
                ->references('id')
                ->on('user_subscriptions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
        });

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_id')->nullable(false)->change();
        });

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->foreign('subscription_id')
                ->references('id')
                ->on('user_subscriptions')
                ->cascadeOnDelete();
        });
    }
};
