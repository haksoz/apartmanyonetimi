<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apartment_data_operations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('apartment_data_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('apartment_data_operations', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Geri alma önkoşulu: user_id boş olan satır kalmamalı.
     * Boş satırlar silinmez ve bir kullanıcıya yazılmaz.
     * Böyle satır varken down() şemaya dokunmadan durur. Dolu satırlar korunur.
     */
    public function down(): void
    {
        $unassigned = DB::table('apartment_data_operations')->whereNull('user_id')->count();

        if ($unassigned > 0) {
            throw new RuntimeException(
                'Geri alma durdu: apartment_data_operations.user_id boş olan '.$unassigned.' kayıt var. İşlem günlükleri silinmedi ve bir kullanıcıya atanmadı. user_id dolu olmadan cascade ilişkisi kurulamaz.'
            );
        }

        Schema::table('apartment_data_operations', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('apartment_data_operations', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('apartment_data_operations', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
