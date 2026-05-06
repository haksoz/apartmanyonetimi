<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_transactions', 'cash_box_id')) {
                $table->foreignId('cash_box_id')->nullable()->after('apartment_id')->constrained('cash_boxes')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('cash_transactions', 'cash_box_id')) {
                $table->dropConstrainedForeignId('cash_box_id');
            }
        });
    }
};
