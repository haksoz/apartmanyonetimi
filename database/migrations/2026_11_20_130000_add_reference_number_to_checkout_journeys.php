<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checkout_journeys', function (Blueprint $table) {
            $table->string('reference_number', 24)->nullable()->unique();
        });

        // Keep every already displayed reference exactly as it was.
        DB::table('checkout_journeys')->whereNull('reference_number')->select('id')->chunkById(500, function ($journeys) {
            foreach ($journeys as $journey) {
                DB::table('checkout_journeys')->where('id', $journey->id)->whereNull('reference_number')
                    ->update(['reference_number' => 'GCS-'.str_pad((string) $journey->id, 6, '0', STR_PAD_LEFT)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('checkout_journeys', function (Blueprint $table) {
            $table->dropUnique(['reference_number']);
            $table->dropColumn('reference_number');
        });
    }
};
