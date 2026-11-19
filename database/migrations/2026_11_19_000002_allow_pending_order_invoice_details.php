<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_invoices', function (Blueprint $table) {
            $table->string('invoice_number', 100)->nullable()->change();
            $table->date('invoice_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_invoices', function (Blueprint $table) {
            $table->string('invoice_number', 100)->nullable(false)->change();
            $table->date('invoice_date')->nullable(false)->change();
        });
    }
};
