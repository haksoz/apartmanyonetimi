<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dues', function (Blueprint $table) {
            if (! Schema::hasColumn('dues', 'remaining_amount')) {
                $table->decimal('remaining_amount', 12, 2)->default(0)->after('amount');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'unallocated_amount')) {
                $table->decimal('unallocated_amount', 12, 2)->default(0)->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dues', function (Blueprint $table) {
            if (Schema::hasColumn('dues', 'remaining_amount')) {
                $table->dropColumn('remaining_amount');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'unallocated_amount')) {
                $table->dropColumn('unallocated_amount');
            }
        });
    }
};
