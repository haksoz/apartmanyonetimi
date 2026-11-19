<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_invoices', function (Blueprint $table) {
            $table->string('invoice_uuid', 64)->nullable()->unique();
            $table->string('xml_sha256', 64)->nullable()->unique();
            $table->longText('xml_content')->nullable();
            $table->json('xml_details')->nullable();
            $table->text('xml_difference_reason')->nullable();
            $table->foreignId('xml_imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('xml_imported_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('xml_imported_by');
            $table->dropUnique(['invoice_uuid']);
            $table->dropUnique(['xml_sha256']);
            $table->dropColumn(['invoice_uuid', 'xml_sha256', 'xml_content', 'xml_details', 'xml_difference_reason', 'xml_imported_at']);
        });
    }
};
