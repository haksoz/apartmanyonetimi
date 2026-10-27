<?php

namespace Tests\Feature;

use App\Models\{Account, AccountTransaction, Apartment, CashBox, CashTransaction, Category, Expense, ExpensePlan, Payment, PaymentAllocation, User};
use App\Support\{CurrentApartment, ExpensePlanGenerator};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

/** Isolated controller/HTTP tests: no migrations, .env database or real apartment data. */
class ExpenseFullPaymentTest extends TestCase
{
    protected Apartment $apartment;
    protected Account $supplier;
    protected CashBox $cashBox;
    protected Category $category;

    public function createApplication()
    {
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        // Override before providers boot or any database use, including cached config.
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $connection = $this->testDatabaseConfiguration();
            $app['config']->set('database.default', $connection['driver']);
            $app['config']->set('database.connections', [$connection['driver'] => $connection]);
            $app['config']->set('cache.default', 'array');
            $app['config']->set('session.driver', 'array');
            $app['config']->set('queue.default', 'sync');
        });
        $app->make(Kernel::class)->bootstrap();
        return $app;
    }

    protected function testDatabaseConfiguration(): array
    {
        return ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true];
    }

    protected function assertIsolatedDatabase(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame('', DB::select('PRAGMA database_list')[0]->file);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertIsolatedDatabase();
        $this->createMemorySchema();
        // Test the production routes/controllers and binding, without membership/subscription fixtures.
        $this->withoutMiddleware(array_values(array_filter(
            $this->app['router']->getMiddleware(), fn ($class) => $class !== SubstituteBindings::class
        )));
        $this->withoutMiddleware([
            \App\Http\Middleware\EnsurePromotedManagerMembership::class,
            \App\Http\Middleware\RequireApartment::class,
            \App\Http\Middleware\EnsureOwner::class,
            \App\Http\Middleware\RedirectBuildingMembers::class,
            \App\Http\Middleware\EnsureSubscriptionAccess::class,
            \App\Http\Middleware\EnsureSubscriptionFeature::class,
        ]);
        $user = new User(['name' => 'Isolated payment test', 'email' => 'isolated@example.test']);
        $user->id = 1;
        $this->actingAs($user);
        $this->apartment = Apartment::create(['user_id' => 1, 'code' => 'TEST', 'name' => 'Memory only']);
        $this->supplier = Account::create(['apartment_id' => $this->apartment->id, 'type' => 'supplier', 'name' => 'Supplier']);
        $this->cashBox = CashBox::create(['apartment_id' => $this->apartment->id, 'name' => 'Cash', 'is_active' => true]);
        $this->category = Category::create(['apartment_id' => $this->apartment->id, 'name' => 'Expense', 'type' => 'expense', 'is_active' => true, 'allow_expense' => true]);
        $this->mock(CurrentApartment::class, function ($mock) {
            $mock->shouldReceive('getFor')->andReturn($this->apartment);
            $mock->shouldReceive('hasAvailableFor')->andReturn(true);
        });
    }

    protected function createMemorySchema(): void
    {
        // Deliberately minimal schema for these controller contracts; never runs migrations.
        $tables = [
            'apartments' => ['user_id', 'code', 'name'],
            'accounts' => ['apartment_id', 'unit_id', 'type', 'name', 'is_active'],
            'categories' => ['apartment_id', 'name', 'type', 'is_active', 'allow_expense'],
            'cash_boxes' => ['apartment_id', 'name', 'is_active'],
            'expenses' => ['apartment_id', 'expense_plan_id', 'plan_occurrence_key', 'account_id', 'category_id', 'category', 'description', 'amount', 'paid_amount', 'remaining_amount', 'expense_date', 'due_date', 'period_month', 'is_paid', 'is_imported', 'reference_number'],
            'payments' => ['apartment_id', 'account_id', 'amount', 'unallocated_amount', 'payment_date', 'method', 'description', 'is_imported', 'reference_number'],
            'payment_allocations' => ['payment_id', 'expense_id', 'due_id', 'amount'],
            'account_transactions' => ['apartment_id', 'account_id', 'transactionable_type', 'transactionable_id', 'type', 'description', 'amount', 'transaction_date', 'is_imported'],
            'cash_transactions' => ['apartment_id', 'cash_box_id', 'account_id', 'expense_id', 'payment_id', 'category_id', 'type', 'description', 'amount', 'transaction_date', 'is_active', 'reference_number'],
            'expense_plans' => ['apartment_id', 'name', 'description', 'description_suffix', 'category_id', 'account_id', 'amount', 'start_date', 'end_date', 'generate_day', 'generation_mode', 'due_days', 'is_active', 'skipped_periods'],
            'units' => ['apartment_id', 'unit_no'],
            'expense_documents' => ['expense_id'],
        ];
        foreach ($tables as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach ($columns as $column) {
                    if (in_array($column, ['amount', 'paid_amount', 'remaining_amount', 'unallocated_amount'])) {
                        $table->decimal($column, 15, 2)->nullable();
                    } elseif (str_ends_with($column, '_id')) {
                        $table->unsignedBigInteger($column)->nullable();
                    } else {
                        $table->string($column)->nullable();
                    }
                }
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    protected function expense(array $attributes = []): Expense
    {
        return Expense::create(array_replace([
            'apartment_id' => $this->apartment->id, 'account_id' => $this->supplier->id,
            'category_id' => $this->category->id, 'amount' => 1000, 'paid_amount' => 0,
            'remaining_amount' => 1000, 'is_paid' => false, 'expense_date' => '2026-10-01',
        ], $attributes));
    }

    protected function payload(array $attributes = []): array
    {
        return array_replace(['cash_box_id' => $this->cashBox->id, 'payment_date' => '2026-10-27', 'amount' => 1000], $attributes);
    }

    protected function counts(): array
    {
        return [Payment::count(), PaymentAllocation::count(), CashTransaction::count(), AccountTransaction::count()];
    }

    private function assertClosed(Expense $expense, int $amount = 1000): void
    {
        $expense->refresh();
        $this->assertTrue($expense->is_paid);
        $this->assertSame(number_format($amount, 2, '.', ''), $expense->paid_amount);
        $this->assertSame('0.00', $expense->remaining_amount);
        $payment = $expense->paymentAllocations()->firstOrFail()->payment;
        $this->assertSame('0.00', $payment->unallocated_amount);
        $this->assertEquals($amount, $payment->amount);
        $this->assertEquals($amount, $payment->allocations()->sum('amount'));
        $this->assertEquals($amount, $payment->transactions()->where('type', 'debit')->sum('amount'));
        $this->assertEquals($amount, $payment->cashTransactions()->where('type', 'expense')->sum('amount'));
    }

    public function test_manual_creation_and_first_full_payment(): void
    {
        $this->post(route('expenses.store'), $this->payload([
            'account_id' => $this->supplier->id, 'category_id' => $this->category->id,
            'expense_date' => '2026-10-01', 'period_month' => '2026-10', 'is_paid' => false,
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $expense = Expense::firstOrFail();
        $this->assertSame('0.00', $expense->paid_amount);
        $this->assertSame('1000.00', $expense->remaining_amount);
        $this->post(route('expenses.payment.store', $expense), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertClosed($expense);
    }

    public function test_creation_as_paid_preserves_full_payment(): void
    {
        $this->post(route('expenses.store'), $this->payload([
            'account_id' => $this->supplier->id, 'category_id' => $this->category->id,
            'expense_date' => '2026-10-01', 'period_month' => '2026-10', 'is_paid' => true,
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertClosed(Expense::firstOrFail());
    }

    public function test_plan_creation_and_first_full_payment(): void
    {
        $plan = ExpensePlan::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->supplier->id,
            'category_id' => $this->category->id, 'name' => 'Test plan', 'amount' => 1000,
            'start_date' => '2026-10-01', 'end_date' => '2026-10-31', 'generate_day' => 1, 'is_active' => true]);
        $this->assertSame(1, app(ExpensePlanGenerator::class)->generateForPeriod($plan, '2026-10'));
        $expense = Expense::firstOrFail();
        $this->assertSame('0.00', $expense->paid_amount);
        $this->assertSame('1000.00', $expense->remaining_amount);
        $this->post(route('expenses.payment.store', $expense), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertClosed($expense);
    }

    public function test_legacy_null_balances_can_be_paid(): void
    {
        $expense = $this->expense(['paid_amount' => null, 'remaining_amount' => null]);
        $this->post(route('expenses.payment.store', $expense), $this->payload())->assertRedirect()->assertSessionHasNoErrors();
        $this->assertClosed($expense);
    }

    public function test_tampered_amounts_are_rejected_without_writes(): void
    {
        $expense = $this->expense();
        foreach ([400, 1001, '1000.001'] as $amount) {
            $this->post(route('expenses.payment.store', $expense), $this->payload(['amount' => $amount]))
                ->assertSessionHasErrors('amount');
            $this->assertSame([0, 0, 0, 0], $this->counts());
            $this->assertFalse($expense->fresh()->is_paid);
        }
    }

    public function test_repeat_and_bulk_after_single_are_rejected(): void
    {
        $expense = $this->expense();
        $this->post(route('expenses.payment.store', $expense), $this->payload())->assertSessionHasNoErrors();
        $before = $this->counts();
        $this->post(route('expenses.payment.store', $expense), $this->payload())->assertSessionHasErrors('amount');
        $other = $this->expense();
        $this->post(route('accounts.expenses.multi-pay.store', $this->supplier), $this->payload(['expense_ids' => "$other->id,$expense->id"]))
            ->assertSessionHasErrors('amount');
        $this->assertSame($before, $this->counts());
        $this->assertFalse($other->fresh()->is_paid);
    }

    public function test_partial_expense_is_rejected_by_both_full_payment_endpoints(): void
    {
        $expense = $this->expense(['paid_amount' => 400, 'remaining_amount' => 600]);
        $payment = Payment::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->supplier->id,
            'amount' => 400, 'unallocated_amount' => 0, 'payment_date' => '2026-10-01']);
        $payment->allocations()->create(['expense_id' => $expense->id, 'amount' => 400]);
        $before = $this->counts();
        $this->post(route('expenses.payment.store', $expense), $this->payload())->assertSessionHasErrors('amount');
        $this->post(route('accounts.expenses.multi-pay.store', $this->supplier), $this->payload(['expense_ids' => (string) $expense->id]))
            ->assertSessionHasErrors('amount');
        $this->assertSame($before, $this->counts());
        $this->assertSame('600.00', $expense->fresh()->remaining_amount);
    }

    public function test_inconsistent_balances_are_not_silently_repaired(): void
    {
        foreach ([['paid_amount' => null], ['paid_amount' => 100], ['remaining_amount' => 800]] as $attributes) {
            $expense = $this->expense($attributes);
            $this->post(route('expenses.payment.store', $expense), $this->payload())->assertSessionHasErrors('amount');
            $this->assertSame([0, 0, 0, 0], $this->counts());
        }
    }

    public function test_bulk_duplicate_ids_are_paid_once_and_repeats_are_rejected(): void
    {
        $first = $this->expense();
        $second = $this->expense(['paid_amount' => null, 'remaining_amount' => null]);
        $payload = $this->payload(['expense_ids' => "$second->id,$first->id,$first->id"]);
        $this->post(route('accounts.expenses.multi-pay.store', $this->supplier), $payload)->assertSessionHasNoErrors();
        $this->assertSame([1, 2, 1, 1], $this->counts());
        $this->assertEquals(2000, Payment::firstOrFail()->amount);
        $this->assertTrue($first->fresh()->is_paid);
        $this->assertTrue($second->fresh()->is_paid);
        $before = $this->counts();
        $this->post(route('accounts.expenses.multi-pay.store', $this->supplier), $payload)->assertSessionHasErrors('amount');
        $this->post(route('expenses.payment.store', $first), $this->payload())->assertSessionHasErrors('amount');
        $this->assertSame($before, $this->counts());
    }

    public function test_bulk_rejects_foreign_deleted_missing_and_invalid_ids_atomically(): void
    {
        $valid = $this->expense();
        $other = Account::create(['apartment_id' => $this->apartment->id, 'type' => 'supplier', 'name' => 'Other']);
        $foreignSupplier = $this->expense(['account_id' => $other->id]);
        $foreignApartment = $this->expense(['apartment_id' => 999]);
        $deleted = $this->expense();
        $deleted->delete();
        foreach ([$foreignSupplier->id, $foreignApartment->id, $deleted->id, 99999, 'abc', ''] as $id) {
            $this->post(route('accounts.expenses.multi-pay.store', $this->supplier), $this->payload(['expense_ids' => "$valid->id,$id"]))
                ->assertSessionHasErrors('expense_ids');
            $this->assertSame([0, 0, 0, 0], $this->counts());
            $this->assertFalse($valid->fresh()->is_paid);
        }
    }

    public function test_single_and_bulk_roll_back_every_financial_write(): void
    {
        $expense = $this->expense();
        // A failure after Payment, cash and ledger inserts must roll everything back.
        PaymentAllocation::creating(fn () => throw new \RuntimeException('isolated rollback probe'));
        try {
            $this->post(route('expenses.payment.store', $expense), $this->payload())->assertStatus(500);
            $this->assertSame([0, 0, 0, 0], $this->counts());
            $this->post(route('accounts.expenses.multi-pay.store', $this->supplier), $this->payload(['expense_ids' => (string) $expense->id]))->assertStatus(500);
            $this->assertSame([0, 0, 0, 0], $this->counts());
            $this->assertFalse($expense->fresh()->is_paid);
        } finally {
            PaymentAllocation::flushEventListeners();
        }
    }

    public function test_bulk_failure_on_second_expense_restores_first_expense(): void
    {
        $first = $this->expense();
        $second = $this->expense();
        $calls = 0;
        PaymentAllocation::creating(function () use (&$calls) {
            if (++$calls === 2) throw new \RuntimeException('second allocation rollback probe');
        });
        try {
            $this->post(route('accounts.expenses.multi-pay.store', $this->supplier),
                $this->payload(['expense_ids' => "$first->id,$second->id"]))->assertStatus(500);
            $this->assertSame([0, 0, 0, 0], $this->counts());
            foreach ([$first, $second] as $expense) {
                $this->assertFalse($expense->fresh()->is_paid);
                $this->assertSame('1000.00', $expense->fresh()->remaining_amount);
            }
        } finally {
            PaymentAllocation::flushEventListeners();
        }
    }

    public function test_supplier_payment_delete_restores_multiple_allocations_to_same_expense(): void
    {
        $expense = $this->expense(['paid_amount' => 400, 'remaining_amount' => 600]);
        $payment = Payment::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->supplier->id,
            'amount' => 400, 'unallocated_amount' => 0, 'payment_date' => '2026-10-01']);
        foreach ([200, 200] as $amount) {
            $payment->allocations()->create(['expense_id' => $expense->id, 'amount' => $amount]);
        }
        $this->delete(route('payments.destroy', $payment))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('0.00', $expense->fresh()->paid_amount);
        $this->assertSame('1000.00', $expense->fresh()->remaining_amount);
        $this->assertSame(0, PaymentAllocation::count());
    }

    public function test_supplier_allocation_rechecks_current_balance(): void
    {
        $expense = $this->expense();
        $payment = Payment::create(['apartment_id' => $this->apartment->id, 'account_id' => $this->supplier->id,
            'amount' => 400, 'unallocated_amount' => 400, 'payment_date' => '2026-10-01']);
        $this->post(route('payments.supplier-allocations.store', $payment), ['allocations' => [['expense_id' => $expense->id, 'amount' => 400]]])
            ->assertSessionHasNoErrors();
        $this->assertSame('600.00', $expense->fresh()->remaining_amount);
        $this->post(route('expenses.payment.store', $expense), $this->payload())->assertSessionHasErrors('amount');
        $before = $this->counts();
        $this->post(route('payments.supplier-allocations.store', $payment), ['allocations' => [['expense_id' => $expense->id, 'amount' => 400]]])
            ->assertSessionHasErrors('allocations');
        $this->assertSame($before, $this->counts());
    }

    public function test_owner_and_tenant_general_receipts_keep_credit_direction(): void
    {
        foreach (['owner', 'tenant'] as $type) {
            $account = Account::create(['apartment_id' => $this->apartment->id, 'type' => $type, 'name' => $type]);
            $this->post(route('payments.store'), $this->payload(['account_id' => $account->id, 'action' => 'save']))
                ->assertRedirect()->assertSessionHasNoErrors();
            $payment = Payment::where('account_id', $account->id)->firstOrFail();
            $this->assertEquals(1000, $payment->transactions()->where('type', 'credit')->sum('amount'));
            $this->assertEquals(1000, $payment->cashTransactions()->where('type', 'income')->sum('amount'));
        }
    }

    public function test_foreign_apartment_single_payment_and_bulk_account_are_rejected(): void
    {
        $expense = $this->expense(['apartment_id' => 999]);
        $this->postJson(route('expenses.payment.store', $expense), $this->payload())->assertNotFound();
        $other = Account::create(['apartment_id' => 999, 'type' => 'supplier', 'name' => 'Foreign']);
        $this->postJson(route('accounts.expenses.multi-pay.store', $other), $this->payload(['expense_ids' => (string) $expense->id]))->assertNotFound();
        $this->assertSame([0, 0, 0, 0], $this->counts());
    }

    public function test_zero_remaining_cannot_create_a_payment(): void
    {
        $expense = $this->expense(['remaining_amount' => 0]);
        $this->post(route('expenses.payment.store', $expense), $this->payload())->assertSessionHasErrors('amount');
        $this->assertSame([0, 0, 0, 0], $this->counts());
    }

    public function test_general_supplier_button_hidden_but_resident_button_preserved(): void
    {
        $html = view('accounts._tabs', ['account' => $this->supplier, 'active' => 'overview'])->render();
        $this->assertStringNotContainsString(route('accounts.supplier-payment.create', $this->supplier), $html);
        foreach (['owner', 'tenant'] as $type) {
            $account = new Account(['type' => $type]);
            $account->id = 10;
            $html = view('accounts._tabs', ['account' => $account, 'active' => 'overview'])->render();
            $this->assertStringContainsString('+ Tahsilat Ekle', $html);
        }
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('accounts.supplier-payment.store'));
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('payments.supplier-allocations.store'));
    }
}
