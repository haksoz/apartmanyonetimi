<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expense_documents', function (Blueprint $table) {
            $table->unsignedInteger('sequence')->default(0)->after('document_type');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_documents', function (Blueprint $table) {
            $table->dropColumn('sequence');
            $table->dropSoftDeletes();
        });
    }
};
