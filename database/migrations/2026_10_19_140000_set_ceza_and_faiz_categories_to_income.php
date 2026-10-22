<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')
            ->whereIn('name', ['Ceza', 'Faiz'])
            ->update([
                'type' => 'income',
                'is_active' => true,
                'is_system' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('categories')
            ->whereIn('name', ['Ceza', 'Faiz'])
            ->where('is_system', true)
            ->update([
                'type' => 'expense',
                'updated_at' => now(),
            ]);
    }
};
