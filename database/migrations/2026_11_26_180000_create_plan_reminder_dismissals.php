<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_reminder_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('plan_type', 20);
            $table->unsignedBigInteger('plan_id');
            $table->string('period', 7);
            $table->timestamps();
            $table->unique(['user_id', 'apartment_id', 'plan_type', 'plan_id', 'period'], 'plan_reminder_user_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_reminder_dismissals');
    }
};
