<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_subscription_id')->unique()->constrained('user_subscriptions')->restrictOnDelete();
            $table->string('invoice_number', 100)->unique();
            $table->date('invoice_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_invoices');
    }
};
