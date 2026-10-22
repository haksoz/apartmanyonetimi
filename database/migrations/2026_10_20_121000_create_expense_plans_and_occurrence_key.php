<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedTinyInteger('generate_day')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('expense_plan_id')->nullable()->after('apartment_id')->constrained('expense_plans')->nullOnDelete();
            $table->string('plan_occurrence_key', 64)->nullable()->after('expense_plan_id');
            $table->unique('plan_occurrence_key', 'expenses_plan_occurrence_key_unique');
        });

        $now = now();
        foreach ([
            ['key' => 'expense_plans', 'name' => 'Gider planlama', 'access' => 'paid'],
            ['key' => 'assessment_plans', 'name' => 'Ek borçlandırma planlama', 'access' => 'paid'],
        ] as $feature) {
            if (! DB::table('features')->where('key', $feature['key'])->exists()) {
                DB::table('features')->insert([
                    'key' => $feature['key'],
                    'name' => $feature['name'],
                    'access' => $feature['access'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique('expenses_plan_occurrence_key_unique');
            $table->dropConstrainedForeignId('expense_plan_id');
            $table->dropColumn('plan_occurrence_key');
        });

        Schema::dropIfExists('expense_plans');

        DB::table('features')->whereIn('key', ['expense_plans', 'assessment_plans'])->delete();
    }
};
