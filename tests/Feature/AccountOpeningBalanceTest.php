<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Apartment;
use App\Models\CashTransaction;
use App\Models\Due;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\User;
use App\Support\AccountOpeningBalance;
use App\Support\CurrentApartment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountOpeningBalanceTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $type = 'tenant'): Account
    {
        $user = User::factory()->create();
        $apartment = Apartment::create(['user_id' => $user->id, 'name' => 'Devir', 'unit_count' => 1]);
        $apartment->members()->attach($user->id, ['role' => 'owner']);
        $unit = Unit::create(['apartment_id' => $apartment->id, 'unit_no' => 1]);
        $this->actingAs($user)->withSession([CurrentApartment::SESSION_KEY => $apartment->id]);

        return Account::create(['apartment_id' => $apartment->id, 'unit_id' => $type === 'supplier' ? null : $unit->id,
            'type' => $type, 'name' => 'Devir hesabı', 'is_active' => true, 'balance' => 0, 'account_opening_date' => '2026-01-01']);
    }

    public function test_all_account_types_and_sides_use_existing_documents_without_cash_or_automatic_allocation(): void
    {
        foreach (['tenant', 'owner', 'supplier'] as $type) {
            foreach (['debit', 'credit'] as $side) {
                $account = $this->account($type);
                app(AccountOpeningBalance::class)->save($account, 1000, $side, '2026-01-01');
                $entry = $account->transactions()->sole();
                $expected = $type === 'supplier'
                    ? ($side === 'credit' ? Expense::class : Payment::class)
                    : ($side === 'debit' ? Due::class : Payment::class);
                $this->assertInstanceOf($expected, $entry->transactionable);
                $this->assertTrue($entry->transactionable->is_opening);
                $this->assertSame('1000.00', $entry->amount);
                $this->assertSame($side, $entry->type);
                $this->assertNull($account->firstActivityDate());
                $this->assertSame(0, CashTransaction::count());
                $this->assertDatabaseCount('payment_allocations', 0);
                app(AccountOpeningBalance::class)->save($account, 1000, $side, '2026-01-01');
                $this->assertSame(1, $account->transactions()->count());
            }
        }
    }

    public function test_existing_account_can_enter_and_correct_opening_from_account_edit(): void
    {
        $account = $this->account();
        $payload = ['type' => 'tenant', 'unit_id' => $account->unit_id, 'name' => $account->name,
            'account_opening_date' => '2026-01-01', 'is_active' => 1, 'balance' => 1000, 'opening_side' => 'credit'];
        $screen = $this->get(route('accounts.edit', $account))->assertOk()->assertSee('name="balance"', false);
        $document = new \DOMDocument;
        @$document->loadHTML($screen->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//form[@data-account-form]//input[@name="balance" or @name="opening_side"]')->length);
        $this->assertSame(1, $xpath->query('//section[@id="opening-balance"]//form//input[@name="balance"]')->length);
        $this->put(route('accounts.opening-balance.update', $account), $payload)->assertSessionHasNoErrors();
        $entry = $account->transactions()->sole();
        $payment = $entry->transactionable;
        $this->assertSame('1000.00', $payment->unallocated_amount);
        $this->put(route('accounts.opening-balance.update', $account), array_replace($payload, ['balance' => 1200]))->assertSessionHasNoErrors();
        $this->assertSame('1200.00', $payment->fresh()->unallocated_amount);
        $this->put(route('accounts.opening-balance.update', $account), array_replace($payload, ['balance' => 1200, 'opening_side' => 'debit']))->assertSessionHasNoErrors();
        $this->assertInstanceOf(Due::class, $entry->fresh()->transactionable);
        $this->assertNull($payment->fresh());
        $this->assertSame(1, $account->transactions()->count());
        $this->assertSame(0, CashTransaction::count());
    }

    public function test_opening_credit_uses_existing_manual_allocation_and_can_be_unallocated(): void
    {
        $account = $this->account();
        app(AccountOpeningBalance::class)->save($account, 1000, 'credit', '2026-01-01');
        $payment = $account->transactions()->sole()->transactionable;
        $due = Due::create(['apartment_id' => $account->apartment_id, 'account_id' => $account->id,
            'unit_id' => $account->unit_id, 'amount' => 750, 'remaining_amount' => 750, 'due_date' => '2026-02-01']);
        $this->assertSame('750.00', $due->fresh()->remaining_amount);
        $this->get(route('payments.allocations.create', $payment))->assertOk();
        $this->post(route('payments.allocations.store', $payment), ['allocations' => [['due_id' => $due->id, 'amount' => 750]]])
            ->assertSessionHasNoErrors()->assertRedirect(route('accounts.show', $account));
        $this->assertSame('250.00', $payment->fresh()->unallocated_amount);
        $this->assertSame('0.00', $due->fresh()->remaining_amount);
        $this->get(route('dues.show', $due))->assertOk()->assertSee('Açılış alacağı buradan silinemez');
        $this->put(route('accounts.opening-balance.update', $account), ['unit_id' => $account->unit_id, 'name' => $account->name,
            'account_opening_date' => '2026-01-01', 'balance' => 900, 'opening_side' => 'credit', 'is_active' => 1])
            ->assertSessionHasErrorsIn('openingBalance', 'balance');
        $this->assertSame('1000.00', $payment->fresh()->amount);
        $allocation = $payment->allocations()->sole();
        $this->delete(route('payments.allocations.destroy', [$payment, $allocation]))->assertSessionHasNoErrors();
        $this->assertSame('1000.00', $payment->fresh()->unallocated_amount);
        $this->assertSame('750.00', $due->fresh()->remaining_amount);
        $this->assertSame(0, CashTransaction::count());
    }

    public function test_legacy_opening_is_linked_without_duplicating_ledger_and_excluded_from_activity(): void
    {
        $account = $this->account('supplier');
        $entry = $account->transactions()->create(['apartment_id' => $account->apartment_id,
            'type' => 'credit', 'amount' => 1000, 'description' => 'Açılış bakiyesi', 'transaction_date' => '2026-01-01']);
        app(AccountOpeningBalance::class)->save($account, 1000, 'credit', '2026-01-01');
        $this->assertSame($entry->id, $account->transactions()->sole()->id);
        $this->assertSame(0.0, (float) Expense::withoutOpening()->sum('amount'));
        $this->assertSame(1000.0, (float) $account->expenses()->sum('remaining_amount'));
        $this->assertSame(0, CashTransaction::count());
    }

    public function test_zero_opening_creates_no_documents_and_unused_opening_can_be_removed(): void
    {
        $account = $this->account();
        $service = app(AccountOpeningBalance::class);
        $service->save($account, 0, null, null);
        $this->assertSame(0, $account->transactions()->count());
        $service->save($account, 1000, 'credit', '2026-01-01');
        $service->save($account, 0, null, null);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('account_transactions', 0);
    }

    public function test_opening_debts_and_supplier_credits_use_existing_allocation_endpoints(): void
    {
        foreach (['tenant', 'owner', 'supplier'] as $type) {
            $account = $this->account($type);
            $supplier = $type === 'supplier';
            app(AccountOpeningBalance::class)->save($account, 1000, $supplier ? 'credit' : 'debit', '2026-01-01');
            $debt = $account->transactions()->sole()->transactionable;
            $payment = Payment::create(['apartment_id' => $account->apartment_id, 'account_id' => $account->id,
                'amount' => 600, 'unallocated_amount' => 600, 'payment_date' => '2026-02-01']);
            $route = $supplier ? 'payments.supplier-allocations.store' : 'payments.allocations.store';
            $this->post(route($route, $payment), ['allocations' => [[
                $supplier ? 'expense_id' : 'due_id' => $debt->id, 'amount' => 600,
            ]]])->assertSessionHasNoErrors();
            $this->assertSame('400.00', $debt->fresh()->remaining_amount);
            $this->assertSame('0.00', $payment->fresh()->unallocated_amount);
            $this->assertSame(0, CashTransaction::count());
        }
        $account = $this->account('supplier');
        app(AccountOpeningBalance::class)->save($account, 1000, 'debit', '2026-01-01');
        $payment = $account->transactions()->sole()->transactionable;
        $expense = Expense::create(['apartment_id' => $account->apartment_id, 'account_id' => $account->id,
            'amount' => 750, 'remaining_amount' => 750, 'paid_amount' => 0, 'is_paid' => false, 'expense_date' => '2026-02-01']);
        $this->get(route('payments.supplier-allocations.create', $payment))->assertOk();
        $this->post(route('payments.supplier-allocations.store', $payment), ['allocations' => [[
            'expense_id' => $expense->id, 'amount' => 750,
        ]]])->assertSessionHasNoErrors();
        $this->assertSame('250.00', $payment->fresh()->unallocated_amount);
        $this->assertSame('0.00', $expense->fresh()->remaining_amount);
        $this->assertSame(0.0, (float) Payment::withoutOpening()->where('account_id', $account->id)->sum('amount'));
        $this->assertSame(0, CashTransaction::count());
    }

    public function test_normal_document_mutations_redirect_openings_to_account_edit(): void
    {
        $account = $this->account();
        app(AccountOpeningBalance::class)->save($account, 1000, 'credit', '2026-01-01');
        $payment = $account->transactions()->sole()->transactionable;
        $this->put(route('payments.update', $payment), ['amount' => 2000])->assertRedirect(route('accounts.edit', $account));
        $this->delete(route('payments.destroy', $payment))->assertRedirect(route('accounts.edit', $account));
        $this->assertSame('1000.00', $payment->fresh()->amount);
        $this->assertSame(0, CashTransaction::count());
    }

    public function test_opening_sources_are_excluded_from_income_expense_reports(): void
    {
        $account = $this->account('supplier');
        $this->grantPaidFeatures($account->apartment);
        app(AccountOpeningBalance::class)->save($account, 3000, 'credit', '2026-01-01');
        $this->assertEquals(0, $this->get(route('reports.expenses', ['month' => '2026-01']))->assertOk()->viewData('total'));
        $account = $this->account();
        $this->grantPaidFeatures($account->apartment);
        app(AccountOpeningBalance::class)->save($account, 1000, 'credit', '2026-01-01');
        $screen = $this->get(route('reports.income-expense', ['month' => '2026-01']))->assertOk();
        $this->assertEquals(0, $screen->viewData('totalIncome'));
        $this->assertEquals(0, $screen->viewData('totalExpense'));
    }

    public function test_current_month_opening_debt_is_shown_as_carried_balance_on_board(): void
    {
        $account = $this->account('owner');
        $this->grantPaidFeatures($account->apartment);
        app(AccountOpeningBalance::class)->save($account, 1000, 'debit', '2026-01-01');
        $screen = $this->get(route('reports.monthly-board', ['month' => '2026-01', 'only_period_residents' => 0]))->assertOk();
        $data = $screen->viewData('accountData')[$account->id];
        $this->assertEquals(1000, $data['pastRemaining']);
        $this->assertEquals(0, $data['selectedAmount']);
        $this->assertEquals(1000, $data['remaining']);
    }

    public function test_existing_opening_migration_preserves_balances_and_is_repeatable(): void
    {
        $account = $this->account('supplier');
        $entry = $account->transactions()->create(['apartment_id' => $account->apartment_id,
            'type' => 'credit', 'amount' => 1000, 'description' => 'Açılış bakiyesi', 'transaction_date' => '2026-01-01']);
        $migration = require database_path('migrations/2026_11_24_000002_link_existing_opening_balances.php');
        $migration->up();
        $migration->up();
        $this->assertSame($entry->id, $account->transactions()->sole()->id);
        $this->assertSame(1000.0, $account->fresh()->ledger_balance);
        $this->assertDatabaseCount('expenses', 1);
        $this->assertDatabaseCount('cash_transactions', 0);
        $migration->down();
        $this->assertNull($entry->fresh()->transactionable_type);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertSame(1000.0, $account->fresh()->ledger_balance);
        $schema = require database_path('migrations/2026_11_24_000001_add_opening_balance_sources.php');
        $schema->down();
        $this->assertFalse(Schema::hasColumn('dues', 'is_opening'));
        $schema->up();
        $migration->up();
        $this->assertDatabaseCount('expenses', 1);
    }
    public function test_profile_update_cannot_accidentally_create_or_replace_opening_balance(): void
    {
        $account = $this->account('supplier');
        $payload = ['name' => 'Yeni ad', 'account_opening_date' => '2026-01-01', 'is_active' => 1];
        $this->put(route('accounts.update', $account), $payload + ['balance' => 1000, 'opening_side' => 'credit'])
            ->assertSessionHasErrors(['balance', 'opening_side']);
        $this->assertSame(0, $account->transactions()->count());
        app(AccountOpeningBalance::class)->save($account, 1000, 'credit', '2026-01-01');
        $this->put(route('accounts.update', $account), $payload + ['balance' => 0])->assertSessionHasNoErrors();
        $this->assertSame('1000.00', $account->transactions()->sole()->amount);
        $this->assertSame('Yeni ad', $account->fresh()->name);
    }

    public function test_opening_requires_explicit_removal_instead_of_zero_amount(): void
    {
        $account = $this->account('supplier');
        app(AccountOpeningBalance::class)->save($account, 1000, 'credit', '2026-01-01');
        $this->put(route('accounts.opening-balance.update', $account), ['balance' => 0, 'opening_side' => 'credit'])
            ->assertSessionHasErrorsIn('openingBalance', 'balance');
        $this->assertSame('1000.00', $account->transactions()->sole()->amount);
        $this->delete(route('accounts.opening-balance.destroy', $account))->assertRedirect(route('accounts.edit', $account));
        $this->assertSame(0, $account->transactions()->count());
        $this->get(route('accounts.edit', $account))->assertOk()->assertSee('Açılış Bakiyesi Tanımla')->assertDontSee('Açılış Bakiyesini Kaldır');
        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_used_opening_is_visibly_locked_and_cannot_be_removed_or_redated(): void
    {
        $account = $this->account('supplier');
        app(AccountOpeningBalance::class)->save($account, 1000, 'debit', '2026-01-01');
        $payment = $account->transactions()->sole()->transactionable;
        $expense = Expense::create(['apartment_id' => $account->apartment_id, 'account_id' => $account->id,
            'amount' => 750, 'remaining_amount' => 750, 'paid_amount' => 0, 'expense_date' => '2026-02-01']);
        $this->post(route('payments.supplier-allocations.store', $payment), ['allocations' => [['expense_id' => $expense->id, 'amount' => 750]]])
            ->assertSessionHasNoErrors();
        $this->get(route('accounts.edit', $account))->assertOk()->assertViewHas('openingLocked', true)
            ->assertViewHas('openingRemaining', 250.0)->assertSee('250,00 TL')->assertSee('Kullanılan Tutar')
            ->assertSee('Bağlı İşlemleri Görüntüle')->assertDontSee('name="balance"', false)->assertDontSee('Açılış Bakiyesini Düzenle');
        $this->put(route('accounts.update', $account), ['name' => 'Güncel tedarikçi', 'is_active' => 1, 'account_opening_date' => '2026-01-01'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Güncel tedarikçi', $account->fresh()->name);
        $this->assertSame('250.00', $payment->fresh()->unallocated_amount);
        $this->delete(route('accounts.opening-balance.destroy', $account))->assertSessionHasErrorsIn('openingBalance', 'balance');
        $this->put(route('accounts.update', $account), ['name' => $account->name, 'is_active' => 1, 'account_opening_date' => '2026-01-02'])
            ->assertSessionHasErrors('account_opening_date');
        $this->assertSame('2026-01-01', $account->fresh()->account_opening_date->toDateString());
        $this->assertSame('250.00', $payment->fresh()->unallocated_amount);
        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_opening_uses_saved_account_date_and_is_scoped_to_current_apartment(): void
    {
        $account = $this->account('supplier');
        $account->update(['account_opening_date' => null]);
        $this->put(route('accounts.opening-balance.update', $account), ['balance' => 1000, 'opening_side' => 'credit', 'account_opening_date' => '2026-01-01'])
            ->assertSessionHasErrorsIn('openingBalance', 'balance');
        $this->assertSame(0, $account->transactions()->count());
        $account->update(['account_opening_date' => '2026-01-01']);
        $this->put(route('accounts.opening-balance.update', $account), ['balance' => 1000, 'opening_side' => 'credit', 'account_opening_date' => '2026-12-01'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-01-01', $account->transactions()->sole()->transaction_date->toDateString());
        $this->account('supplier');
        $this->put(route('accounts.opening-balance.update', $account), ['balance' => 2000, 'opening_side' => 'credit'])->assertNotFound();
        $this->delete(route('accounts.opening-balance.destroy', $account))->assertNotFound();
        $this->assertSame('1000.00', $account->transactions()->sole()->amount);
    }

    public function test_opening_due_detail_exposes_management_without_aidat_targeting_or_normal_mutations(): void
    {
        $account = $this->account();
        app(AccountOpeningBalance::class)->save($account, 600, 'debit', '2026-01-01');
        $due = $account->transactions()->sole()->transactionable;
        $target = Account::create(['apartment_id' => $account->apartment_id, 'unit_id' => $account->unit_id,
            'type' => 'owner', 'name' => 'Malik', 'is_active' => true]);
        $destination = route('accounts.edit', $account).'#opening-balance';
        $this->get(route('dues.show', $due))->assertOk()->assertSee('Açılış Borcu')->assertSee('Hesap Açılış Bakiyesi')
            ->assertSee('Açılış Bakiyesini Yönet')->assertSee($destination, false)
            ->assertDontSee('Kiracı Öncelikli')->assertDontSee('Borç Aktar')->assertDontSee('Aidatı Sil')
            ->assertDontSee(route('dues.edit', $due), false)->assertDontSee(route('dues.destroy', $due).'"', false)
            ->assertSee('Tahsil Et');
        $this->get(route('dues.edit', $due))->assertRedirect($destination)->assertSessionMissing('error');
        $this->patch(route('dues.update', $due), ['amount' => 900])->assertRedirect($destination);
        $this->delete(route('dues.destroy', $due))->assertRedirect($destination);
        $this->post(route('dues.transfer', $due), ['target_account_id' => $target->id])->assertRedirect($destination);
        $this->assertSame($account->id, $due->fresh()->account_id);
        $this->assertSame('600.00', $due->fresh()->amount);
        $this->assertSame(1, $account->transactions()->count());
    }

    public function test_opening_filter_is_distinct_from_aidat_and_manual_but_included_in_all_open_debts(): void
    {
        $account = $this->account();
        app(AccountOpeningBalance::class)->save($account, 600, 'debit', '2026-01-01');
        $opening = $account->transactions()->sole()->transactionable;
        $normal = Due::create(['apartment_id' => $account->apartment_id, 'account_id' => $account->id,
            'unit_id' => $account->unit_id, 'amount' => 750, 'remaining_amount' => 750, 'due_date' => '2026-02-01']);
        foreach ([['due_type' => 'opening'], ['source' => 'opening']] as $filter) {
            $screen = $this->get(route('dues.index', $filter))->assertOk()->assertSee('Hesap Açılış Bakiyesi');
            $this->assertSame([$opening->id], $screen->viewData('dues')->pluck('id')->all());
        }
        foreach ([['due_type' => 'aidat'], ['source' => 'manual']] as $filter) {
            $screen = $this->get(route('dues.index', $filter))->assertOk();
            $this->assertSame([$normal->id], $screen->viewData('dues')->pluck('id')->all());
        }
        $screen = $this->get(route('dues.index', ['status' => 'open']))->assertOk();
        $this->assertEqualsCanonicalizing([$opening->id, $normal->id], $screen->viewData('dues')->pluck('id')->all());
        $this->assertEquals(1350, $screen->viewData('dues')->sum('remaining_amount'));
        $this->delete(route('dues.bulk-destroy'), ['ids' => $opening->id.','.$normal->id])->assertRedirect();
        $this->assertNotNull($opening->fresh());
        $this->assertTrue($normal->fresh()->trashed());
    }

    public function test_due_export_respects_opening_and_aidat_filters(): void
    {
        $account = $this->account();
        $this->grantPaidFeatures($account->apartment);
        app(AccountOpeningBalance::class)->save($account, 600, 'debit', '2026-01-01');
        Due::create(['apartment_id' => $account->apartment_id, 'account_id' => $account->id,
            'unit_id' => $account->unit_id, 'amount' => 750, 'remaining_amount' => 750, 'description' => 'Normal aidat', 'due_date' => '2026-02-01']);
        foreach ([['source' => 'opening'], ['due_type' => 'opening'], ['due_type' => 'aidat']] as $filter) {
            $response = $this->get(route('dues.export', $filter))->assertOk();
            $path = tempnam(sys_get_temp_dir(), 'opening-export-');
            try {
                file_put_contents($path, $response->streamedContent());
                $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
                $this->assertEquals($filter === ['due_type' => 'aidat'] ? 750 : 600, $sheet->getCell('F5')->getValue());
                $this->assertNull($sheet->getCell('F6')->getValue());
                if ($filter !== ['due_type' => 'aidat']) {
                    $this->assertSame('Hesap Açılış Bakiyesi', $sheet->getCell('C5')->getValue());
                }
            } finally {
                unlink($path);
            }
        }
    }

}
