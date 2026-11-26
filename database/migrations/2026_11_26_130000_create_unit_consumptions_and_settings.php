<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumption_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('consumption_type', 20);
            $table->string('measurement_unit', 30);
            $table->string('method', 10);
            $table->timestamps();
            $table->unique(['apartment_id', 'consumption_type']);
        });
        Schema::create('unit_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->string('consumption_type', 20);
            $table->date('period');
            $table->string('measurement_unit', 30);
            $table->string('method', 10);
            $table->decimal('value', 16, 4);
            $table->decimal('previous_value', 16, 4)->nullable();
            $table->decimal('consumption', 16, 4);
            $table->timestamps();
            $table->unique(['apartment_id', 'unit_id', 'consumption_type', 'period'], 'unit_consumption_period_unique');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('unit_consumptions');
        Schema::dropIfExists('consumption_settings');
    }
};
