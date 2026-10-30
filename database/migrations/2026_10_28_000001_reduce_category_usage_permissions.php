<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            foreach (['dues', 'due_batches', 'due_plans', 'assessment_plans'] as $table) {
                $invalid = DB::table($table)->whereIn('due_type', ['masraf_yansitma', 'ceza', 'faiz'])->first();
                if ($invalid) {
                    throw new RuntimeException("{$table} tablosunda kaldırılan borç türünde kayıt var (ID: {$invalid->id}). Veri eşleştirmesi yapılmadan devam edilemez.");
                }
            }

            foreach (DB::table('apartments')->select('id', 'default_aidat_category_id')->get() as $apartment) {
                foreach (['Masraf Yansıtma', 'Ceza', 'Faiz'] as $name) {
                    // Pasif ve silinmiş kategoriler yeniden etkinleştirilmez.
                    $categories = DB::table('categories')->where('apartment_id', $apartment->id)->where('name', $name)->get();
                    foreach ($categories as $category) {
                        if ($category->id === $apartment->default_aidat_category_id) {
                            throw new RuntimeException("Apartman {$apartment->id}: Genel Aidat referansı {$name} kategorisini gösteriyor.");
                        }
                    }
                    if ($categories->isEmpty()) {
                        DB::table('categories')->insert([
                            'apartment_id' => $apartment->id, 'name' => $name, 'type' => 'income',
                            'is_active' => true, 'is_system' => false,
                            'allow_aidat' => true, 'allow_malik_payi' => true, 'allow_expense' => false,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('categories')->whereIn('id', $categories->pluck('id'))->update([
                            'allow_aidat' => true, 'allow_malik_payi' => true, 'allow_expense' => false,
                        ]);
                    }
                }
            }
            // Eski üç sütun geri dönüş/veri incelemesi için tutulur; uygulama bunları kullanmaz.
            // Diğer kategorilerin yetkileri ve mevcut finansal kayıtlar değiştirilmez.
        });
    }

    public function down(): void
    {
        // Sonradan yönetici tarafından değiştirilen yetkileri ezmemek için veri geri alınmaz.
        // Kategoriler ve yetkiler korunur. Tam veri geri dönüşü geçiş öncesi yedekten yapılır.
    }
};
