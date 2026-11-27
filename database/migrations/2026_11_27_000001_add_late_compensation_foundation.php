<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->date('late_compensation_starts_on')->nullable();
        });

        Schema::create('late_compensation_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->boolean('is_active')->default(false);
            $table->decimal('monthly_rate', 10, 6);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['apartment_id', 'effective_from'], 'late_comp_policy_apartment_date_unique');
        });

        Schema::table('dues', function (Blueprint $table) {
            $table->string('production_source', 48)->nullable();
            $table->boolean('late_compensation_applies')->default(false);
            $table->date('late_compensation_starts_on')->nullable();
        });

        foreach (['due_plans', 'assessment_plans'] as $planTable) {
            Schema::table($planTable, function (Blueprint $table) {
                $table->boolean('late_compensation_applies')->default(false);
            });
        }

        Schema::create('payment_allocation_instructions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 16);
            $table->decimal('requested_amount', 12, 2);
            $table->uuid('operation_group')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_allocation_instruction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instruction_id')->constrained('payment_allocation_instructions', 'id', 'allocation_item_instruction_fk')->cascadeOnDelete();
            $table->foreignId('due_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->decimal('requested_amount', 12, 2);
            $table->timestamps();
            $table->unique(['instruction_id', 'position'], 'allocation_item_instruction_position_unique');
        });

        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->decimal('principal_amount', 12, 2)->nullable();
            $table->decimal('late_compensation_amount', 12, 2)->default(0);
            $table->foreignId('allocation_instruction_id')->nullable()
                ->constrained('payment_allocation_instructions')->nullOnDelete();
        });

        Schema::table('account_transactions', function (Blueprint $table) {
            $table->foreignId('late_compensation_allocation_id')->nullable()
                ->constrained('payment_allocations')->restrictOnDelete();
            $table->unique('late_compensation_allocation_id', 'account_late_comp_allocation_unique');
        });
    }

    public function down(): void
    {
        Schema::table('account_transactions', function (Blueprint $table) {
            $table->dropForeign(['late_compensation_allocation_id']);
            $table->dropUnique('account_late_comp_allocation_unique');
            $table->dropColumn('late_compensation_allocation_id');
        });

        Schema::table('payment_allocations', function (Blueprint $table) {
            $table->dropForeign(['allocation_instruction_id']);
            $table->dropColumn(['allocation_instruction_id', 'principal_amount', 'late_compensation_amount']);
        });

        Schema::dropIfExists('payment_allocation_instruction_items');
        Schema::dropIfExists('payment_allocation_instructions');

        foreach (['due_plans', 'assessment_plans'] as $planTable) {
            Schema::table($planTable, fn (Blueprint $table) => $table->dropColumn('late_compensation_applies'));
        }

        Schema::table('dues', function (Blueprint $table) {
            $table->dropColumn(['production_source', 'late_compensation_applies', 'late_compensation_starts_on']);
        });

        Schema::dropIfExists('late_compensation_policies');
        Schema::table('apartments', fn (Blueprint $table) => $table->dropColumn('late_compensation_starts_on'));
    }
};
