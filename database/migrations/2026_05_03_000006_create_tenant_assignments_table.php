<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('move_in_date');
            $table->date('move_out_date')->nullable();
            $table->timestamps();

            $table->index(['apartment_id', 'unit_id', 'move_out_date']);
            $table->index(['account_id', 'move_out_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_assignments');
    }
};
