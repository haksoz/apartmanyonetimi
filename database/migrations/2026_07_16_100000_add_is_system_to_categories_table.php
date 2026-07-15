<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('is_active');
            }
        });

        $systemCategories = [
            ['name' => 'Aidat', 'type' => 'income'],
            ['name' => 'Demirbaş', 'type' => 'all'],
            ['name' => 'Diğer', 'type' => 'all'],
        ];

        foreach (DB::table('apartments')->pluck('id') as $apartmentId) {
            foreach ($systemCategories as $systemCategory) {
                DB::table('categories')
                    ->updateOrInsert(
                        ['apartment_id' => $apartmentId, 'name' => $systemCategory['name']],
                        [
                            'type' => $systemCategory['type'],
                            'is_active' => true,
                            'is_system' => true,
                        ]
                    );
            }
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            if (Schema::hasColumn('categories', 'is_system')) {
                $table->dropColumn('is_system');
            }
        });
    }
};
