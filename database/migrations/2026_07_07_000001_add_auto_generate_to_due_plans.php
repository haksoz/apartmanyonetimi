<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('due_plans', function (Blueprint $table) {
            $table->boolean('auto_generate')->default(false)->after('due_day');
            $table->unsignedTinyInteger('generate_day')->default(1)->after('auto_generate');
        });
    }

    public function down(): void
    {
        Schema::table('due_plans', function (Blueprint $table) {
            $table->dropColumn(['auto_generate', 'generate_day']);
        });
    }
};
