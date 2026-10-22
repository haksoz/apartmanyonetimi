<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $systemCategories = [
            ['name' => 'Ceza', 'type' => 'income'],
            ['name' => 'Faiz', 'type' => 'income'],
        ];

        $now = now();

        foreach (DB::table('apartments')->pluck('id') as $apartmentId) {
            foreach ($systemCategories as $systemCategory) {
                $existing = DB::table('categories')
                    ->where('apartment_id', $apartmentId)
                    ->where('name', $systemCategory['name'])
                    ->first();

                if ($existing) {
                    DB::table('categories')
                        ->where('id', $existing->id)
                        ->update([
                            'type' => $systemCategory['type'],
                            'is_active' => true,
                            'is_system' => true,
                            'deleted_at' => null,
                            'updated_at' => $now,
                        ]);

                    continue;
                }

                DB::table('categories')->insert([
                    'apartment_id' => $apartmentId,
                    'name' => $systemCategory['name'],
                    'type' => $systemCategory['type'],
                    'is_active' => true,
                    'is_system' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('categories')
            ->whereIn('name', ['Ceza', 'Faiz'])
            ->where('is_system', true)
            ->update([
                'is_system' => false,
                'updated_at' => now(),
            ]);
    }
};
