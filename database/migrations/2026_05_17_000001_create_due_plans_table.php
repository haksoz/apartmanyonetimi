<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('due_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->string('amount_type')->default('monthly'); // monthly | yearly
            $table->decimal('monthly_amount', 10, 2)->nullable();
            $table->decimal('yearly_amount', 10, 2)->nullable();
            $table->string('distribution_type')->default('equal'); // equal | square_meters | share_coefficient
            $table->string('target_audience')->default('tenant_priority'); // tenant_priority | owner_only
            $table->unsignedTinyInteger('due_day')->default(1); // Her ayın kaçında (1-28)
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('due_batches', function (Blueprint $table) {
            $table->foreignId('due_plan_id')->nullable()->after('apartment_id')->constrained('due_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('due_batches', function (Blueprint $table) {
            $table->dropForeign(['due_plan_id']);
            $table->dropColumn('due_plan_id');
        });

        Schema::dropIfExists('due_plans');
    }
};
