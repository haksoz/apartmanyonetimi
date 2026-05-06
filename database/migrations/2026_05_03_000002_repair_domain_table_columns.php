<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            if (! Schema::hasColumn('units', 'apartment_id')) {
                $table->foreignId('apartment_id')->nullable()->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('units', 'unit_no')) {
                $table->string('unit_no')->nullable();
            }
            if (! Schema::hasColumn('units', 'floor')) {
                $table->string('floor')->nullable();
            }
            if (! Schema::hasColumn('units', 'block')) {
                $table->string('block')->nullable();
            }
            if (! Schema::hasColumn('units', 'resident_name')) {
                $table->string('resident_name')->nullable();
            }
            if (! Schema::hasColumn('units', 'phone')) {
                $table->string('phone')->nullable();
            }
        });

        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'apartment_id')) {
                $table->foreignId('apartment_id')->nullable()->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('accounts', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('accounts', 'type')) {
                $table->string('type')->default('resident');
            }
            if (! Schema::hasColumn('accounts', 'name')) {
                $table->string('name')->nullable();
            }
            if (! Schema::hasColumn('accounts', 'phone')) {
                $table->string('phone')->nullable();
            }
            if (! Schema::hasColumn('accounts', 'email')) {
                $table->string('email')->nullable();
            }
            if (! Schema::hasColumn('accounts', 'balance')) {
                $table->decimal('balance', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('accounts', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });

        Schema::table('account_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('account_transactions', 'apartment_id')) {
                $table->foreignId('apartment_id')->nullable()->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('account_transactions', 'account_id')) {
                $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('account_transactions', 'type')) {
                $table->string('type')->nullable();
            }
            if (! Schema::hasColumn('account_transactions', 'description')) {
                $table->string('description')->nullable();
            }
            if (! Schema::hasColumn('account_transactions', 'amount')) {
                $table->decimal('amount', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('account_transactions', 'transaction_date')) {
                $table->date('transaction_date')->nullable();
            }
        });

        Schema::table('dues', function (Blueprint $table) {
            if (! Schema::hasColumn('dues', 'apartment_id')) {
                $table->foreignId('apartment_id')->nullable()->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('dues', 'unit_id')) {
                $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('dues', 'account_id')) {
                $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('dues', 'period')) {
                $table->string('period')->nullable();
            }
            if (! Schema::hasColumn('dues', 'amount')) {
                $table->decimal('amount', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('dues', 'due_date')) {
                $table->date('due_date')->nullable();
            }
            if (! Schema::hasColumn('dues', 'status')) {
                $table->string('status')->default('unpaid');
            }
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasColumn('expenses', 'apartment_id')) {
                $table->foreignId('apartment_id')->nullable()->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'account_id')) {
                $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('expenses', 'category')) {
                $table->string('category')->nullable();
            }
            if (! Schema::hasColumn('expenses', 'description')) {
                $table->string('description')->nullable();
            }
            if (! Schema::hasColumn('expenses', 'amount')) {
                $table->decimal('amount', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('expenses', 'expense_date')) {
                $table->date('expense_date')->nullable();
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'apartment_id')) {
                $table->foreignId('apartment_id')->nullable()->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('payments', 'account_id')) {
                $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('payments', 'due_id')) {
                $table->foreignId('due_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('payments', 'amount')) {
                $table->decimal('amount', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('payments', 'payment_date')) {
                $table->date('payment_date')->nullable();
            }
            if (! Schema::hasColumn('payments', 'method')) {
                $table->string('method')->nullable();
            }
            if (! Schema::hasColumn('payments', 'description')) {
                $table->string('description')->nullable();
            }
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_transactions', 'apartment_id')) {
                $table->foreignId('apartment_id')->nullable()->constrained()->cascadeOnDelete();
            }
            if (! Schema::hasColumn('cash_transactions', 'account_id')) {
                $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            }
            if (! Schema::hasColumn('cash_transactions', 'type')) {
                $table->string('type')->nullable();
            }
            if (! Schema::hasColumn('cash_transactions', 'description')) {
                $table->string('description')->nullable();
            }
            if (! Schema::hasColumn('cash_transactions', 'amount')) {
                $table->decimal('amount', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('cash_transactions', 'transaction_date')) {
                $table->date('transaction_date')->nullable();
            }
        });
    }

    public function down(): void
    {
    }
};
