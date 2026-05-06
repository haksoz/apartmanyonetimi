<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('all');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['apartment_id', 'name']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('account_id')->constrained('categories')->nullOnDelete();
            }
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_transactions', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('account_id')->constrained('categories')->nullOnDelete();
            }
        });

        $defaults = [
            ['name' => 'Aidat', 'type' => 'income'],
            ['name' => 'Demirbaş', 'type' => 'income'],
            ['name' => 'Elektrik', 'type' => 'expense'],
            ['name' => 'Su', 'type' => 'expense'],
            ['name' => 'Asansör', 'type' => 'expense'],
            ['name' => 'Temizlik', 'type' => 'expense'],
            ['name' => 'Yönetim', 'type' => 'expense'],
            ['name' => 'Bakım', 'type' => 'expense'],
            ['name' => 'Diğer', 'type' => 'all'],
        ];

        foreach (DB::table('apartments')->pluck('id') as $apartmentId) {
            foreach ($defaults as $default) {
                DB::table('categories')->insertOrIgnore([
                    'apartment_id' => $apartmentId,
                    'name' => $default['name'],
                    'type' => $default['type'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        foreach (DB::table('expenses')->whereNotNull('category')->get() as $expense) {
            $category = DB::table('categories')
                ->where('apartment_id', $expense->apartment_id)
                ->where('name', $expense->category)
                ->first();

            if (! $category) {
                $categoryId = DB::table('categories')->insertGetId([
                    'apartment_id' => $expense->apartment_id,
                    'name' => $expense->category,
                    'type' => 'expense',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $categoryId = $category->id;
            }

            DB::table('expenses')->where('id', $expense->id)->update(['category_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('cash_transactions', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }
        });

        Schema::dropIfExists('categories');
    }
};
