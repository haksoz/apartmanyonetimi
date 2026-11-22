<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_bands', function (Blueprint $table) {
            $table->boolean('per_unit')->default(false);
        });

        // Keep identifiers, campaign references and existing flat prices intact.
        foreach ([[1, 14], [15, 28], [29, 50], [51, 100], [101, null]] as $index => [$min, $max]) {
            $band = DB::table('price_bands')->where('sort_order', $index + 1)->first();
            if (! $band) {
                continue;
            }
            $values = [
                'label' => $max === null ? '101+ daire' : $min.'–'.$max.' daire',
                'min_units' => $min,
                'max_units' => $max,
                'per_unit' => $max === null,
                'is_quote' => false,
                'updated_at' => now(),
            ];
            if ($max === null) {
                // A negotiated total must never become a per-unit rate.
                $values['monthly_price'] = null;
                $values['yearly_price'] = null;
            }
            DB::table('price_bands')->where('id', $band->id)->update($values);
        }
    }

    public function down(): void
    {
        Schema::table('price_bands', fn (Blueprint $table) => $table->dropColumn('per_unit'));
    }
};
