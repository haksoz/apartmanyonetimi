<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasColumn('due_batches', 'category_id')) {
            Schema::withoutForeignKeyConstraints(fn () => Schema::table('due_batches', fn (Blueprint $table) => $table->dropConstrainedForeignId('category_id')));
        }
    }

    public function down(): void
    {
        // Original group category values require the pre-migration backup.
        Schema::withoutForeignKeyConstraints(fn () => Schema::table('due_batches', fn (Blueprint $table) => $table
            ->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete()));
    }
};
