<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payment_attempts', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable();
            $table->boolean('review_required')->default(false);
        });
        Schema::create('subscription_payment_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_attempt_id')->constrained('subscription_payment_attempts')->restrictOnDelete();
            $table->string('fingerprint', 64)->unique();
            $table->string('provider_status', 16);
            $table->unsignedBigInteger('total_amount_minor');
            $table->unsignedBigInteger('payment_amount_minor')->nullable();
            $table->string('reported_currency', 8)->nullable();
            $table->string('reported_payment_type', 16)->nullable();
            $table->boolean('reported_test_mode');
            $table->string('failed_reason_code', 32)->nullable();
            $table->string('outcome', 24);
            $table->string('reason', 64)->nullable();
            $table->unsignedInteger('deliveries')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payment_notifications');
        Schema::table('subscription_payment_attempts', function (Blueprint $table) {
            $table->dropColumn(['submitted_at', 'review_required']);
        });
    }
};
