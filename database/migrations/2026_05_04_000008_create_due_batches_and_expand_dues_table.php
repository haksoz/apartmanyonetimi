<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('due_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('source_type');
            $table->string('distribution_type')->default('equal');
            $table->string('period');
            $table->date('source_period')->nullable();
            $table->json('category_filter_ids')->nullable();
            $table->decimal('source_amount', 12, 2)->default(0);
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('dues', function (Blueprint $table) {
            if (! Schema::hasColumn('dues', 'due_batch_id')) {
                $table->foreignId('due_batch_id')->nullable()->after('id')->constrained('due_batches')->nullOnDelete();
            }
            if (! Schema::hasColumn('dues', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('account_id')->constrained('categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('dues', 'description')) {
                $table->string('description')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dues', function (Blueprint $table) {
            if (Schema::hasColumn('dues', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('dues', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }
            if (Schema::hasColumn('dues', 'due_batch_id')) {
                $table->dropConstrainedForeignId('due_batch_id');
            }
        });

        Schema::dropIfExists('due_batches');
    }
};
