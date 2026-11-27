<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dues', fn (Blueprint $table) => $table->date('collection_account_since')->nullable());
        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->foreignId('allocation_instruction_item_id')->nullable()
                ->constrained('payment_allocation_instruction_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dues', fn (Blueprint $table) => $table->dropColumn('collection_account_since'));
        Schema::table('payment_allocations', fn (Blueprint $table) =>
            $table->dropConstrainedForeignId('allocation_instruction_item_id'));
    }
};
