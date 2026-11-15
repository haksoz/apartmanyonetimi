<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payment_attempts', function (Blueprint $table) {
            $table->text('iframe_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payment_attempts', function (Blueprint $table) {
            $table->dropColumn(['iframe_token', 'token_expires_at']);
        });
    }
};
