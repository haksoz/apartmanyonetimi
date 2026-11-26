<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('due_batches', function (Blueprint $table) {
            $table->foreignId('consumption_period_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('due_batches', fn (Blueprint $table) => $table->dropConstrainedForeignId('consumption_period_id'));
    }
};
