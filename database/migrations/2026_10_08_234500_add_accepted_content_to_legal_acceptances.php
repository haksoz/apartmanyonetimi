<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_acceptances', function (Blueprint $table) {
            $table->longText('accepted_content')->nullable()->after('legal_document_version_id');
        });
    }

    public function down(): void
    {
        Schema::table('legal_acceptances', function (Blueprint $table) {
            $table->dropColumn('accepted_content');
        });
    }
};
