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
        Schema::table('apartments', function (Blueprint $table) {
            $table->string('code', 6)->nullable()->unique()->after('id');
        });

        \App\Models\Apartment::whereNull('code')->each(function ($apartment) {
            $apartment->update(['code' => \App\Models\Apartment::generateCode()]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('apartments', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
