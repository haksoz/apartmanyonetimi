<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->boolean('is_imported')->default(false)->after('is_paid');
            $table->decimal('paid_amount', 15, 2)->nullable()->after('amount');
            $table->decimal('remaining_amount', 15, 2)->nullable()->after('paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['is_imported', 'paid_amount', 'remaining_amount']);
        });
    }
};
