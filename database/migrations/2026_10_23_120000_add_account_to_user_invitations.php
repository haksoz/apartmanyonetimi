<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_invitations', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreignId('account_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('email')->nullable();
        });
    }

    public function down(): void
    {
        // Account invitations cannot be represented by the previous schema.
        DB::table('user_invitations')->whereNull('user_id')->delete();
        Schema::table('user_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn('email');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
