<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cash_transactions', 'category_id')) {
            Schema::table('cash_transactions', fn (Blueprint $table) => $table->dropConstrainedForeignId('category_id'));
        }
    }

    public function down(): void
    {
        // Historical category values require the pre-migration data backup.
        Schema::table('cash_transactions', fn (Blueprint $table) => $table
            ->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete());
    }
};
