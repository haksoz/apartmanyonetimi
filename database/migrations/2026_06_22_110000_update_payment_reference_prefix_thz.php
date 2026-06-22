<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Kat maliki ve kiracı hesaplarına ait ödemelerin (tahsilatların)
        // referans numarasındaki ODE önekini THZ olarak güncelle.
        DB::statement("
            UPDATE payments p
            INNER JOIN accounts a ON a.id = p.account_id
            SET p.reference_number = CONCAT('THZ', SUBSTRING(p.reference_number, 4))
            WHERE a.type IN ('owner', 'tenant')
              AND p.reference_number LIKE 'ODE-%'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE payments p
            INNER JOIN accounts a ON a.id = p.account_id
            SET p.reference_number = CONCAT('ODE', SUBSTRING(p.reference_number, 4))
            WHERE a.type IN ('owner', 'tenant')
              AND p.reference_number LIKE 'THZ-%'
        ");
    }
};
