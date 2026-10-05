<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('subscription_no', 20)->unique();
            $table->foreignId('apartment_id')->nullable()->constrained('apartments')->nullOnDelete();
            $table->string('status', 20)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->foreignId('subscription_id')->nullable()->after('id')->constrained('subscriptions')->nullOnDelete();
        });

        Schema::table('subscription_items', function (Blueprint $table) {
            $table->foreignId('apartment_subscription_id')->nullable()->after('id')->constrained('subscriptions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('apartment_subscription_id');
        });

        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
        });

        Schema::dropIfExists('subscriptions');
    }
};
