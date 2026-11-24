<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            DB::table('cash_boxes')->where('name', 'Devir Öncesi Kasası')
                ->update(['name' => 'İçe Aktarma Kasası']);
            DB::table('accounts')->where('type', 'supplier')->where('name', 'Devir Öncesi Tedarikçi')
                ->update(['name' => 'İçe Aktarma Tedarikçi']);

            foreach ([
                'Devir Öncesi Kasası — import işlemleri için otomatik oluşturuldu.' => 'İçe Aktarma Kasası — import işlemleri için otomatik oluşturuldu.',
                'Devir Öncesi Kasası — gider import işlemleri için otomatik oluşturuldu.' => 'İçe Aktarma Kasası — gider import işlemleri için otomatik oluşturuldu.',
            ] as $old => $new) {
                DB::table('cash_boxes')->where('description', $old)->update(['description' => $new]);
            }

            foreach (['payments', 'account_transactions', 'cash_transactions'] as $table) {
                DB::table($table)->where('description', 'Devir Öncesi')
                    ->update(['description' => 'İçe Aktarma']);
            }

            DB::table('payments')->where('description', 'like', 'Devir Öncesi: %')
                ->orderBy('id')->chunkById(100, function ($payments) {
                    foreach ($payments as $payment) {
                        DB::table('payments')->where('id', $payment->id)->update([
                            'description' => 'İçe Aktarma: '.mb_substr($payment->description, mb_strlen('Devir Öncesi: ')),
                        ]);
                    }
                });
        });
    }

    public function down(): void
    {
        // Terminology changes do not need to restore the old labels.
    }
};
