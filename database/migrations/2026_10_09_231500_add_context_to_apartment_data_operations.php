<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apartment_data_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('source_apartment_id')->nullable()->after('apartment_id');
            $table->string('apartment_name')->nullable()->after('source_apartment_id');
            $table->unsignedInteger('unit_count_before')->nullable()->after('apartment_name');
            $table->string('actor_name')->nullable()->after('user_id');
            $table->string('subscription_no_before')->nullable()->after('unit_count_before');
            $table->string('archive_reference')->nullable()->after('archive_status');
            $table->timestamp('archive_retain_until')->nullable()->after('archive_reference');
        });
    }

    public function down(): void
    {
        Schema::table('apartment_data_operations', function (Blueprint $table) {
            $table->dropColumn([
                'source_apartment_id',
                'apartment_name',
                'unit_count_before',
                'actor_name',
                'subscription_no_before',
                'archive_reference',
                'archive_retain_until',
            ]);
        });
    }
};
