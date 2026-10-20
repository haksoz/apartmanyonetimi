<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('total_amount', 12, 2);
            $table->string('distribution_type')->default('equal');
            $table->string('target_audience')->default('owner_only');
            $table->string('due_type')->default('malik_payi');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('installment_count');
            $table->string('generation_mode', 32)->default('create_all_now');
            $table->unsignedTinyInteger('generate_day')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('assessment_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_plan_id')->constrained('assessment_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('due_date');
            $table->string('period', 7);
            $table->string('status', 20)->default('pending');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('due_batch_id')->nullable()->constrained('due_batches')->nullOnDelete();
            $table->timestamps();

            $table->unique(['assessment_plan_id', 'sequence']);
        });

        Schema::create('assessment_unit_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_plan_id')->constrained('assessment_plans')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->decimal('share_amount', 12, 2);
            $table->decimal('weight', 12, 4)->nullable();
            $table->decimal('square_meters', 10, 2)->nullable();
            $table->decimal('share_coefficient', 10, 4)->nullable();
            $table->timestamps();

            $table->unique(['assessment_plan_id', 'unit_id']);
        });

        Schema::create('assessment_installment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_plan_id')->constrained('assessment_plans')->cascadeOnDelete();
            $table->foreignId('assessment_installment_id')->constrained('assessment_installments')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->foreignId('due_id')->nullable()->constrained('dues')->nullOnDelete();
            $table->timestamps();

            $table->unique(['assessment_installment_id', 'unit_id'], 'assessment_installment_unit_unique');
        });

        Schema::table('due_batches', function (Blueprint $table) {
            $table->foreignId('assessment_plan_id')->nullable()->after('due_plan_id')->constrained('assessment_plans')->nullOnDelete();
            $table->foreignId('assessment_installment_id')->nullable()->after('assessment_plan_id')->constrained('assessment_installments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('due_batches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assessment_installment_id');
            $table->dropConstrainedForeignId('assessment_plan_id');
        });

        Schema::dropIfExists('assessment_installment_lines');
        Schema::dropIfExists('assessment_unit_shares');
        Schema::dropIfExists('assessment_installments');
        Schema::dropIfExists('assessment_plans');
    }
};
