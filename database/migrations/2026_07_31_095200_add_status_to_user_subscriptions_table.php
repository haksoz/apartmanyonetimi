<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_subscriptions', 'status')) {
            Schema::table('user_subscriptions', function (Blueprint $table) {
                $table->string('status')->default('active')->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_subscriptions', 'status')) {
            Schema::table('user_subscriptions', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
