<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->uuid('checkout_token')->nullable()->unique();
            $table->string('checkout_fingerprint', 64)->nullable();
        });
        Schema::create('subscription_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('user_subscriptions')->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('merchant_oid', 64)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('TRY');
            $table->boolean('is_test')->default(false);
            $table->text('payer_snapshot')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->foreignId('payment_attempt_id')->nullable()->unique()->constrained('subscription_payment_attempts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_attempt_id');
        });
        Schema::dropIfExists('subscription_payment_attempts');
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropUnique(['checkout_token']);
            $table->dropColumn(['checkout_token', 'checkout_fingerprint']);
        });
    }
};
