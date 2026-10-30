<?php

namespace Tests\Feature;

use App\Models\{Account, Apartment, AssessmentPlan, CashBox, CashTransaction, Due, DueBatch, DuePlan, Payment, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class TargetAudienceRemovalMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_removal_preserves_financial_rows_and_rollback_only_restores_schema(): void
    {
        $this->assertSame('sqlite', DB::getDriverName());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Migration fixture', 'unit_count' => 1]);
        $account = Account::create(['apartment_id' => $apartment->id, 'type' => 'owner', 'name' => 'Owner']);
        $aidat = DuePlan::create(['apartment_id' => $apartment->id, 'name' => 'Aidat', 'plan_year' => 2026,
            'amount_type' => 'monthly', 'monthly_amount' => 100, 'distribution_type' => 'equal', 'due_day' => 1, 'generate_day' => 1]);
        $assessment = AssessmentPlan::create(['apartment_id' => $apartment->id, 'name' => 'Repair', 'total_amount' => 100,
            'distribution_type' => 'equal', 'installment_count' => 1, 'generation_mode' => 'on_schedule', 'generate_day' => 1]);
        foreach (['aidat' => ['due_plan_id' => $aidat->id], 'malik_payi' => ['assessment_plan_id' => $assessment->id]] as $type => $link) {
            $batch = DueBatch::create([...$link, 'apartment_id' => $apartment->id, 'due_type' => $type,
                'source_type' => 'manual', 'distribution_type' => 'equal', 'period' => '2026-06', 'source_amount' => 100]);
            $due = Due::create(['apartment_id' => $apartment->id, 'account_id' => $account->id, 'due_type' => $type,
                'due_batch_id' => $batch->id, 'amount' => 100, 'remaining_amount' => 100, 'due_date' => '2026-06-01', 'status' => 'unpaid']);
            if ($type === 'malik_payi') {
                $due->delete();
                $deletedId = $due->id;
                continue;
            }
            $payment = Payment::create(['apartment_id' => $apartment->id, 'account_id' => $account->id,
                'amount' => 25, 'unallocated_amount' => 25, 'payment_date' => '2026-06-01']);
            $payment->allocateToDue($due, 25);
            $due->transactions()->create(['apartment_id' => $apartment->id, 'account_id' => $account->id,
                'type' => 'debit', 'amount' => 100, 'transaction_date' => '2026-06-01']);
            $payment->transactions()->create(['apartment_id' => $apartment->id, 'account_id' => $account->id,
                'type' => 'credit', 'amount' => 25, 'transaction_date' => '2026-06-01']);
            $box = CashBox::create(['apartment_id' => $apartment->id, 'name' => 'Cash']);
            CashTransaction::create(['apartment_id' => $apartment->id, 'cash_box_id' => $box->id,
                'account_id' => $account->id, 'payment_id' => $payment->id, 'type' => 'income', 'amount' => 25,
                'transaction_date' => '2026-06-01']);
        }

        $tables = ['dues', 'due_batches', 'due_plans', 'assessment_plans', 'accounts', 'payments',
            'payment_allocations', 'account_transactions', 'cash_boxes', 'cash_transactions'];
        $snapshot = function () use ($tables): array {
            return collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()
                ->map(fn ($row) => collect((array) $row)->except('target_audience')->all())->all()])->all();
        };
        $before = $snapshot();
        $migration = require database_path('migrations/2026_10_29_000001_drop_target_audience_from_due_tables.php');
        // Recreate the old schema only inside this disposable in-memory connection.
        $migration->down();
        DB::table('dues')->where('id', $deletedId)->update(['target_audience' => null]);
        DB::table('due_batches')->where('due_type', 'aidat')->update(['target_audience' => 'historical-marker']);
        $migration->up();
        foreach (['dues', 'due_batches', 'due_plans', 'assessment_plans'] as $table) {
            $this->assertFalse(Schema::hasColumn($table, 'target_audience'));
        }
        $this->assertSame($before, $snapshot());
        $this->assertTrue(Due::withTrashed()->findOrFail($deletedId)->trashed());

        $migration->down();
        $this->assertSame($before, $snapshot());
        $this->assertNull(DB::table('dues')->where('id', $deletedId)->value('target_audience'));
        $this->assertSame('tenant_priority', DB::table('due_batches')->where('due_type', 'aidat')->value('target_audience'));
        // Rollback cannot recover the original historical marker.
        $migration->up();
    }
}
