<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('due_batches', function (Blueprint $table) {
            $table->timestamp('consumption_cancelled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('due_batches', fn (Blueprint $table) => $table->dropColumn('consumption_cancelled_at'));
    }
};
