<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Kat maliki ve kiracı hesaplarına ait ödemelerin (tahsilatların)
        // referans numarasındaki ODE önekini THZ olarak güncelle (SQLite uyumlu).
        $payments = DB::table('payments')
            ->join('accounts', 'accounts.id', '=', 'payments.account_id')
            ->whereIn('accounts.type', ['owner', 'tenant'])
            ->where('payments.reference_number', 'like', 'ODE-%')
            ->select('payments.id', 'payments.reference_number')
            ->get();

        foreach ($payments as $payment) {
            $newReference = 'THZ' . substr($payment->reference_number, 3);
            DB::table('payments')->where('id', $payment->id)->update(['reference_number' => $newReference]);
        }
    }

    public function down(): void
    {
        $payments = DB::table('payments')
            ->join('accounts', 'accounts.id', '=', 'payments.account_id')
            ->whereIn('accounts.type', ['owner', 'tenant'])
            ->where('payments.reference_number', 'like', 'THZ-%')
            ->select('payments.id', 'payments.reference_number')
            ->get();

        foreach ($payments as $payment) {
            $newReference = 'ODE' . substr($payment->reference_number, 3);
            DB::table('payments')->where('id', $payment->id)->update(['reference_number' => $newReference]);
        }
    }
};
