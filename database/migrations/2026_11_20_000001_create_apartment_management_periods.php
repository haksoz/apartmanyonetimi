<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apartment_management_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('original_apartment_id')->index();
            $table->string('apartment_name');
            $table->string('apartment_code')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('manager_name');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->boolean('is_current')->default(false);
            $table->string('start_source', 32);
            $table->string('end_source', 32)->nullable();
            $table->string('source_key')->unique();
            $table->timestamps();
            $table->index(['user_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('apartment_management_periods');
    }
};
