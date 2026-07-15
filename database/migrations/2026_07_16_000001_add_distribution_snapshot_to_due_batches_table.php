<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('due_batches', function (Blueprint $table) {
            if (! Schema::hasColumn('due_batches', 'distribution_snapshot')) {
                $table->json('distribution_snapshot')->nullable()->after('source_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('due_batches', function (Blueprint $table) {
            if (Schema::hasColumn('due_batches', 'distribution_snapshot')) {
                $table->dropColumn('distribution_snapshot');
            }
        });
    }
};
