<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('due_plans')->update(['generation_mode' => 'on_schedule', 'auto_generate' => true]);
    }

    public function down(): void
    {
        // Aidat planları her zaman zamanlanmış olarak çalışır.
    }
};
