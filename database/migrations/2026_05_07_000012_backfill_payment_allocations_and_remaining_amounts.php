<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_allocations')) {
            return;
        }

        DB::transaction(function () {
            DB::table('dues')->update([
                'remaining_amount' => DB::raw('amount'),
            ]);

            DB::table('payments')->update([
                'unallocated_amount' => DB::raw('amount'),
            ]);

            $allocations = DB::table('payments')
                ->whereNotNull('due_id')
                ->where('amount', '>', 0)
                ->select('id as payment_id', 'due_id', 'amount', DB::raw('CURRENT_TIMESTAMP as created_at'), DB::raw('CURRENT_TIMESTAMP as updated_at'))
                ->get();

            if ($allocations->isNotEmpty()) {
                $rows = $allocations->map(fn ($allocation) => (array) $allocation)->toArray();
                DB::table('payment_allocations')->insert($rows);
            }

            DB::update(
                'UPDATE dues SET remaining_amount = CASE WHEN amount - COALESCE((SELECT SUM(amount) FROM payment_allocations WHERE payment_allocations.due_id = dues.id), 0) < 0 THEN 0 ELSE amount - COALESCE((SELECT SUM(amount) FROM payment_allocations WHERE payment_allocations.due_id = dues.id), 0) END'
            );

            DB::update(
                'UPDATE payments SET unallocated_amount = CASE WHEN amount - COALESCE((SELECT SUM(amount) FROM payment_allocations WHERE payment_allocations.payment_id = payments.id), 0) < 0 THEN 0 ELSE amount - COALESCE((SELECT SUM(amount) FROM payment_allocations WHERE payment_allocations.payment_id = payments.id), 0) END'
            );

            DB::update(
                'UPDATE dues SET status = CASE WHEN remaining_amount = 0 THEN "paid" WHEN remaining_amount = amount THEN "unpaid" ELSE "partial" END'
            );
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            DB::table('payment_allocations')->delete();

            DB::table('dues')->update([
                'remaining_amount' => 0,
            ]);

            DB::table('payments')->update([
                'unallocated_amount' => 0,
            ]);
        });
    }
};
