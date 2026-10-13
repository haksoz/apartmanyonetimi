<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apartment_data_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('apartment_id')->nullable()->constrained('apartments')->nullOnDelete();
            $table->foreignId('outcome_apartment_id')->nullable()->constrained('apartments')->nullOnDelete();
            $table->string('action');
            $table->string('result');
            $table->string('archive_status');
            $table->unsignedInteger('requested_unit_count')->nullable();
            $table->text('scope')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apartment_data_operations');
    }
};
