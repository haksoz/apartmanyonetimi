<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'due_id')) {
                $table->dropForeign(['due_id']);
                $table->dropColumn('due_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'due_id')) {
                $table->foreignId('due_id')->nullable()->constrained()->nullOnDelete();
            }
        });
    }
};
