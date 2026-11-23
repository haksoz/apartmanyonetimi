<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_test_customer')->default(false)->index());
        Schema::table('system_settings', fn (Blueprint $table) => $table->boolean('show_test_customers')->default(false));
    }

    public function down(): void
    {
        Schema::table('system_settings', fn (Blueprint $table) => $table->dropColumn('show_test_customers'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_test_customer']);
            $table->dropColumn('is_test_customer');
        });
    }
};
