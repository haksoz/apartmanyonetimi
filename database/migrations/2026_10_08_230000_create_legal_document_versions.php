<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_document_versions', function (Blueprint $table) {
            $table->id();
            $table->string('document_key');
            $table->string('version');
            $table->string('title');
            $table->longText('body');
            $table->char('content_sha256', 64);
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['document_key', 'version']);
        });

        Schema::table('legal_acceptances', function (Blueprint $table) {
            $table->foreignId('legal_document_version_id')
                ->nullable()
                ->after('document_version')
                ->constrained('legal_document_versions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('legal_acceptances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('legal_document_version_id');
        });

        Schema::dropIfExists('legal_document_versions');
    }
};
