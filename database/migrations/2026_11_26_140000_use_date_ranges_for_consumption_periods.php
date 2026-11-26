<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('unit_consumptions')->exists()) {
            throw new RuntimeException('Tarih aralıklı dönem dönüşümü için tüketim tablosu boş olmalıdır.');
        }
        if (! Schema::hasTable('consumption_periods')) {
            Schema::create('consumption_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('apartment_id')->constrained()->cascadeOnDelete();
                $table->string('consumption_type', 20);
                $table->date('start_date');
                $table->date('end_date');
                $table->string('measurement_unit', 30);
                $table->string('method', 10);
                $table->timestamps();
                $table->unique(['apartment_id', 'consumption_type', 'start_date', 'end_date'], 'consumption_period_dates_unique');
            });
        }
        if (! Schema::hasIndex('unit_consumptions', 'unit_consumptions_apartment_lookup')) {
            Schema::table('unit_consumptions', function (Blueprint $table) {
                $table->index('apartment_id', 'unit_consumptions_apartment_lookup');
            });
        }
        Schema::table('unit_consumptions', function (Blueprint $table) {
            $table->dropUnique('unit_consumption_period_unique');
            $table->dropColumn('period');
            $table->foreignId('consumption_period_id')->constrained()->restrictOnDelete();
            $table->unique(['consumption_period_id', 'unit_id'], 'unit_consumption_period_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('unit_consumptions')->exists()) {
            throw new RuntimeException('Tüketim kayıtları varken dönem dönüşümü geri alınamaz.');
        }
        Schema::table('unit_consumptions', function (Blueprint $table) {
            $table->dropForeign(['consumption_period_id']);
            $table->dropUnique('unit_consumption_period_unique');
            $table->dropColumn('consumption_period_id');
            $table->date('period');
            $table->unique(['apartment_id', 'unit_id', 'consumption_type', 'period'], 'unit_consumption_period_unique');
        });
        Schema::dropIfExists('consumption_periods');
    }
};
